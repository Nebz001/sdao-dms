<?php

namespace App\Approval;

use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Organizations\StudentTrackData;

/**
 * The `fix` prop every returned-document edit page (Fix and Resend) receives:
 * what was flagged, by whom, where the resend goes, and any saved draft.
 * Read only; the rules themselves live in ReturnedEditLock.
 *
 * `active` is false when the return carried nothing fixable (no flags, only
 * "General", or only sections with nothing to edit). The page then stays a
 * fully editable form and shows the return note instead.
 */
final class FixResendData
{
    /**
     * @return array{
     *     active: bool,
     *     formLabel: string,
     *     organization: string|null,
     *     returnedAt: string|null,
     *     returnedBy: array{name: string|null, role: string}|null,
     *     role: string,
     *     comment: string|null,
     *     items: list<array{key: string, label: string, comment: string|null, kind: string}>,
     *     notes: list<array{label: string, comment: string|null}>,
     *     resendLabel: string,
     *     back: array{href: string, title: string},
     *     draft: array<string, array<string, mixed>>|null,
     *     draftUrl: string,
     * }
     */
    public static function for(Document $document): array
    {
        $document->loadMissing(['organization', 'workflowTemplate.steps', 'transitions.actor']);

        $returned = $document->transitions
            ->filter(fn (DocumentTransition $t) => $t->action === TransitionAction::Returned)
            ->sortBy('id')
            ->last();
        $step = $document->workflowTemplate?->steps->firstWhere('position', $document->current_step_position);
        $role = $step === null ? 'the approver' : StudentTrackData::stepLabel($step->role);
        $general = SectionFlags::currentComment($document);
        $notes = SectionFlags::currentSectionComments($document);
        $fixable = ReturnedEditLock::fixableKeys($document);
        $labels = self::labels($document);

        return [
            'active' => $fixable !== [],
            'formLabel' => self::formLabel($document->form_type),
            'organization' => $document->organization?->name,
            'returnedAt' => $returned?->created_at?->toIso8601String(),
            'returnedBy' => $returned === null ? null : ['name' => $returned->actor?->name, 'role' => $role],
            'role' => $role,
            'comment' => $general,
            'items' => collect($fixable)
                ->map(fn (string $key) => [
                    'key' => $key,
                    'label' => $labels[$key] ?? $key,
                    'comment' => $notes[$key] ?? $general,
                    'kind' => self::kind($document, $key),
                ])
                ->values()
                ->all(),
            // Flagged, but with nothing to edit on this page (General, Event Details, ...).
            'notes' => collect(SectionFlags::currentlyFlagged($document))
                ->reject(fn (string $key) => in_array($key, $fixable, true))
                ->map(fn (string $key) => ['label' => $labels[$key] ?? $key, 'comment' => $notes[$key] ?? null])
                ->values()
                ->all(),
            'resendLabel' => 'Resend to '.($step === null ? 'approver' : StudentTrackData::stepLabel($step->role)),
            'back' => self::back($document),
            'draft' => FixDraft::get($document),
            'draftUrl' => route('documents.fix-draft', $document),
        ];
    }

    /** @return array<string, string> */
    private static function labels(Document $document): array
    {
        if ($document->form_type !== FormType::ActivityCalendar) {
            return SectionFlags::labelsFor($document->form_type);
        }

        $document->loadMissing('activityCalendar.activities');

        return $document->activityCalendar?->activities
            ->values()
            ->mapWithKeys(fn ($row, int $index) => ["activity_{$index}" => 'Activity '.($index + 1).': '.$row->name])
            ->all() ?? [];
    }

    private static function kind(Document $document, string $key): string
    {
        return match (true) {
            $document->form_type === FormType::ActivityCalendar => 'row',
            collect(AttachmentSlots::for($document->form_type))->contains(fn ($slot) => $slot->key === $key) => 'file',
            default => 'section',
        };
    }

    private static function formLabel(FormType $formType): string
    {
        return match ($formType) {
            FormType::OrganizationRegistration => 'Registration',
            FormType::OrganizationRenewal => 'Renewal',
            default => $formType->label(),
        };
    }

    /**
     * Where "Back" goes: the page the officer came from (Track Documents or
     * My Documents), remembered across a failed resend that reloads this page.
     *
     * @return array{href: string, title: string}
     */
    private static function back(Document $document): array
    {
        $sessionKey = "fix-resend.back.{$document->id}";
        $previous = url()->previous();
        $path = parse_url($previous, PHP_URL_PATH) ?: '';
        $sameSite = parse_url($previous, PHP_URL_HOST) === request()->getHost();
        $known = [
            '/track' => ['href' => route('track.index'), 'title' => 'Track Documents'],
            '/document-history' => ['href' => route('document-history.index'), 'title' => 'My Documents'],
        ];

        if ($sameSite && isset($known[$path])) {
            session()->put($sessionKey, $known[$path]);
        }

        return session($sessionKey, $known['/track']);
    }
}
