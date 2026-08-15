<?php

namespace App\Support;

use App\Http\Controllers\NotificationController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Single source of truth for shaping a stored notification into the array
 * shape the frontend consumes — shared by HandleInertiaRequests::share()
 * (the bell's capped `notifications` prop) and NotificationController::index()
 * (the full /notifications page), so the two surfaces can never drift into
 * different field names or a different url-normalization rule.
 *
 * @see HandleInertiaRequests::notificationsFor()
 * @see NotificationController::index()
 */
class NotificationPresenter
{
    /**
     * @return array{id: string, kind: ?string, title: string, body: string, status: ?string, url: ?string, readAt: ?string, createdAt: string}
     */
    public static function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => $notification->data['kind'] ?? null,
            'title' => $notification->data['title'] ?? '',
            'body' => $notification->data['body'] ?? '',
            // Only meaningfully populated for document_outcome
            // (approved/rejected/returned) — the bell uses it to pick the
            // same CircleCheck/CircleX/Undo2 icon ApprovalActionsCard
            // already uses for those outcomes.
            'status' => $notification->data['status'] ?? null,
            'url' => self::toRelativePath($notification->data['url'] ?? null),
            'readAt' => $notification->read_at?->toIso8601String(),
            'createdAt' => $notification->created_at->toIso8601String(),
        ];
    }

    /**
     * Reduces a notification's stored `url` to an origin-relative path
     * before it reaches the client. New rows already store a path (see
     * App\Support\DocumentUrls::pathForReviewer()/pathForSubmitter() and the
     * account/join-request notifications' own `route(..., absolute: false)`
     * calls) — this is a defensive normalization for rows written before
     * that change, or by a queue worker/CLI context with a different
     * APP_URL than whatever origin the browser is actually on. An absolute
     * URL baked at write time is a cross-origin URL the moment those
     * differ, and Inertia's router.visit() issues an XHR with no
     * same-origin fallback, so the bell click would silently fail — see
     * DocumentUrls' class docblock.
     */
    private static function toRelativePath(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'])) {
            // Already relative (or unparseable) — leave it as-is.
            return $url;
        }

        $path = $parts['path'] ?? '/';

        if (isset($parts['query'])) {
            $path .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $path .= '#'.$parts['fragment'];
        }

        return $path;
    }
}
