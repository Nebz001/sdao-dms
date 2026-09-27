<?php

namespace App\Jobs;

use App\ActivityProposals\ProposalReference;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendExpoPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 20;

    public function __construct(
        public readonly int $userId,
        public readonly int $documentId,
        public readonly int $stepPosition,
        public readonly TransitionAction $triggerAction,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $document = Document::query()->find($this->documentId);

        if ($user === null || $document === null
            || $document->form_type !== FormType::ActivityProposal
            || $document->status !== DocumentStatus::InReview
            || $document->current_step_position !== $this->stepPosition
            || ! Gate::forUser($user)->allows('review', $document)) {
            return;
        }

        foreach (PushToken::query()->where('user_id', $user->id)->get() as $registration) {
            $this->sendToRegistration($registration, $document);
        }
    }

    private function sendToRegistration(PushToken $registration, Document $document): void
    {
        $token = $registration->token;
        try {
            $response = $this->expoRequest()->post(config('services.expo.push_url'), [
                'to' => $token,
                'title' => 'Activity Proposal needs your review',
                'body' => $this->triggerAction === TransitionAction::Resubmitted
                    ? 'A proposal is ready for your review again.'
                    : 'A proposal is ready for your review.',
                'data' => ['proposalReference' => ProposalReference::format($document)],
                'channelId' => 'reviews',
                'sound' => 'default',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Expo push request failed', [
                'push_token_id' => $registration->id,
                'document_id' => $document->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        if (! $response->successful()) {
            Log::warning('Expo push request returned an HTTP failure', [
                'push_token_id' => $registration->id,
                'document_id' => $document->id,
                'http_status' => $response->status(),
            ]);

            if ($response->status() === 429 || $response->serverError()) {
                throw new RuntimeException('Expo Push Service returned a retryable HTTP failure.');
            }

            return;
        }

        $ticket = $response->json('data');

        if (is_array($ticket) && array_is_list($ticket)) {
            $ticket = $ticket[0] ?? null;
        }

        if (! is_array($ticket) || ! is_string($ticket['status'] ?? null)) {
            Log::warning('Expo push response did not contain a ticket', [
                'push_token_id' => $registration->id,
                'document_id' => $document->id,
            ]);

            return;
        }

        if ($ticket['status'] === 'error') {
            $error = data_get($ticket, 'details.error', 'UnknownError');
            Log::warning('Expo push ticket reported an error', [
                'push_token_id' => $registration->id,
                'document_id' => $document->id,
                'expo_error' => $error,
            ]);

            if ($error === 'DeviceNotRegistered') {
                PushToken::query()
                    ->whereKey($registration->id)
                    ->where('token', $token)
                    ->delete();
            }

            if ($error === 'MessageRateExceeded') {
                throw new RuntimeException('Expo Push Service rate-limited this device.');
            }

            return;
        }

        if (! is_string($ticket['id'] ?? null) || $ticket['id'] === '') {
            return;
        }

        CheckExpoPushReceipt::dispatch(
            $registration->id,
            $ticket['id'],
            hash('sha256', $token),
        )->onConnection('database')->delay(now()->addMinutes(15));
    }

    private function expoRequest(): PendingRequest
    {
        $request = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
        $accessToken = config('services.expo.access_token');

        if (is_string($accessToken) && $accessToken !== '') {
            $request = $request->withToken($accessToken);
        }

        return $request;
    }
}
