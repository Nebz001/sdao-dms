<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\FormType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ApproverHandOffNotification;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    private const int PER_PAGE = 15;

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $notifications = $this->notificationsFor($user)
            ->latest('created_at')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'data' => collect($notifications->items())
                ->map(fn (DatabaseNotification $notification): array => $this->present($notification))
                ->values(),
            'meta' => [
                'unread_count' => $this->countUnread($user),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'links' => [
                'prev' => $notifications->previousPageUrl(),
                'next' => $notifications->nextPageUrl(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'unread_count' => $this->countUnread($this->user($request)),
            ],
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $this->notificationsFor($this->user($request))
            ->whereKey($notification)
            ->firstOrFail();

        $record->markAsRead();

        return response()->json([
            'data' => [
                'id' => $record->id,
                'read_at' => $record->fresh()->read_at?->toISOString(),
            ],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->notificationsFor($this->user($request))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => ['unread_count' => 0],
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return MorphMany<DatabaseNotification, User> */
    private function notificationsFor(User $user): MorphMany
    {
        $notifications = $user->notifications()
            ->where('type', ApproverHandOffNotification::class);

        if ($user->getConnection()->getDriverName() === 'pgsql') {
            return $notifications->whereRaw(
                'cast("data" as jsonb) ->> \'form_type\' = ?',
                [FormType::ActivityProposal->value],
            );
        }

        return $notifications->where('data->form_type', FormType::ActivityProposal->value);
    }

    private function countUnread(User $user): int
    {
        return $this->notificationsFor($user)->whereNull('read_at')->count();
    }

    /**
     * @return array{id: string, type: string, title: string, body: string, proposal_reference: string|null, read_at: string|null, created_at: string}
     */
    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => 'activity_proposal_ready_for_review',
            'title' => $notification->data['title'] ?? 'Proposal ready for review',
            'body' => $notification->data['body'] ?? 'An Activity Proposal is waiting in your review queue.',
            'proposal_reference' => $notification->data['proposal_reference'] ?? null,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at->toISOString(),
        ];
    }
}
