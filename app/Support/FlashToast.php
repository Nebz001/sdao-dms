<?php

namespace App\Support;

/**
 * Builds the payload for `redirect(...)->with('flash', ...)`, the app's one
 * toast convention (read by HandleInertiaRequests::share(), rendered by
 * resources/js/components/ui/sonner.tsx).
 *
 * Every toast is a short `title` (what happened) plus a `message` (what it
 * means) and, optionally, text-link actions. `Undo` actions POST to a real
 * revert endpoint; view links are plain GET visits.
 *
 * @phpstan-type ToastAction array{label: string, href: string, method: 'get'|'post'}
 * @phpstan-type ToastPayload array{title: string, message: string, type?: string, actions?: list<ToastAction>}
 */
final class FlashToast
{
    /**
     * @param  list<array{label: string, href: string, method: 'get'|'post'}>  $actions
     * @return array{title: string, message: string, type?: string, actions?: list<array{label: string, href: string, method: 'get'|'post'}>}
     */
    public static function make(string $title, string $message, string $type = 'success', array $actions = []): array
    {
        $payload = ['title' => $title, 'message' => $message];

        if ($type !== 'success') {
            $payload['type'] = $type;
        }

        if ($actions !== []) {
            $payload['actions'] = $actions;
        }

        return $payload;
    }

    /**
     * @param  list<array{label: string, href: string, method: 'get'|'post'}>  $actions
     * @return array{title: string, message: string, type?: string, actions?: list<array{label: string, href: string, method: 'get'|'post'}>}
     */
    public static function error(string $title, string $message, array $actions = []): array
    {
        return self::make($title, $message, 'error', $actions);
    }

    /**
     * @param  list<array{label: string, href: string, method: 'get'|'post'}>  $actions
     * @return array{title: string, message: string, type?: string, actions?: list<array{label: string, href: string, method: 'get'|'post'}>}
     */
    public static function warning(string $title, string $message, array $actions = []): array
    {
        return self::make($title, $message, 'warning', $actions);
    }

    /**
     * @return array{label: string, href: string, method: 'post'}
     */
    public static function undo(string $url): array
    {
        return ['label' => 'Undo', 'href' => $url, 'method' => 'post'];
    }

    /**
     * A toast action that POSTs to a real endpoint under a label that says what
     * it does — for when the generic "Undo" would be ambiguous (e.g. the
     * deactivation toast, where the reverse restores the account but NOT the
     * officer seat that ended with it).
     *
     * @return array{label: string, href: string, method: 'post'}
     */
    public static function postAction(string $label, string $url): array
    {
        return ['label' => $label, 'href' => $url, 'method' => 'post'];
    }

    /**
     * @return array{label: string, href: string, method: 'get'}
     */
    public static function link(string $label, string $url): array
    {
        return ['label' => $label, 'href' => $url, 'method' => 'get'];
    }
}
