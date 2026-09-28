<?php

namespace App\Jobs;

use App\Models\PushToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CheckExpoPushReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 20;

    public function __construct(
        public readonly int $pushTokenId,
        public readonly string $ticketId,
        public readonly string $tokenHash,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 600, 900];
    }

    public function handle(): void
    {
        try {
            $response = $this->expoRequest()->post(config('services.expo.receipts_url'), [
                'ids' => [$this->ticketId],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Expo receipt request failed', [
                'push_token_id' => $this->pushTokenId,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        if (! $response->successful()) {
            Log::warning('Expo receipt request returned an HTTP failure', [
                'push_token_id' => $this->pushTokenId,
                'http_status' => $response->status(),
            ]);

            if ($response->status() === 429 || $response->serverError()) {
                throw new RuntimeException('Expo Push Service returned a retryable receipt failure.');
            }

            return;
        }

        $receipt = $response->json("data.{$this->ticketId}");

        if (! is_array($receipt)) {
            $this->release(300);

            return;
        }

        if (($receipt['status'] ?? null) !== 'error') {
            return;
        }

        $error = data_get($receipt, 'details.error', 'UnknownError');
        Log::warning('Expo push receipt reported an error', [
            'push_token_id' => $this->pushTokenId,
            'expo_error' => $error,
        ]);

        if ($error === 'DeviceNotRegistered') {
            $registration = PushToken::query()->find($this->pushTokenId);

            if ($registration !== null && hash_equals($this->tokenHash, hash('sha256', $registration->token))) {
                $registration->delete();
            }

            return;
        }

        if ($error === 'MessageRateExceeded') {
            throw new RuntimeException('Expo Push Service rate-limited this receipt.');
        }
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
