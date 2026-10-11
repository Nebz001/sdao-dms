<?php

namespace App\Dashboard;

use App\Enums\AccountStatus;
use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Models\DocumentTransition;
use App\Models\OfficerChangeRequest;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The one banner at the top of the SDAO home page, picked in priority order:
 * red when documents have sat with an approver past the follow-up threshold,
 * orange when SDAO itself has work waiting, green when nothing is. There is no
 * blue "renewal period" state yet: the system stores no renewal close date
 * (see OpenRenewalSeason), so there is nothing true to show for it.
 *
 * Reads the same InReviewSnapshot rows as every other admin figure, so the
 * banner can never disagree with the tiles below it.
 */
class AdminHomeBanner
{
    /** A document idle with one approver for more than this many days is "stuck". */
    public const int STUCK_AFTER_DAYS = InReviewSnapshot::SDAO_FOLLOW_UP_DAYS;

    public function __construct(private readonly InReviewSnapshot $snapshot) {}

    /**
     * @return array{tone: string, icon: string, title: string, subtitle: string, buttonLabel: string, href: string}
     */
    public function payload(): array
    {
        $rows = $this->snapshot->rows();
        $stuck = $rows->filter(fn (StuckDocument $row) => $row->idleDays > self::STUCK_AFTER_DAYS);

        if ($stuck->isNotEmpty()) {
            $where = $stuck
                ->countBy(fn (StuckDocument $row) => $row->stepRole->stepLabel())
                ->sortDesc()
                ->map(fn (int $count, string $label) => "{$count} with ".($label === 'SDAO' ? $label : "the {$label}"))
                ->values()
                ->implode(', ');

            return [
                'tone' => 'red',
                'icon' => 'alert-circle',
                'title' => $stuck->count().' '.Str::plural('document', $stuck->count()).' '.($stuck->count() === 1 ? 'is' : 'are').' stuck in routing',
                'subtitle' => 'Waiting over '.self::STUCK_AFTER_DAYS." days: {$where}.",
                'buttonLabel' => 'View stuck documents',
                'href' => route('admin.stuck-documents.index', ['waiting_on' => 'approver']),
            ];
        }

        $accounts = User::query()->where('account_status', AccountStatus::Unverified->value)->count();
        $changes = OfficerChangeRequest::query()->pending()->count();
        $sdaoDocuments = $rows->filter->isSdaoStep()->countBy(fn (StuckDocument $row) => $row->document->form_type->value);

        $parts = [];

        if ($accounts > 0) {
            $parts[] = $accounts.' new officer '.Str::plural('account', $accounts).' to verify';
        }

        foreach ($sdaoDocuments as $type => $count) {
            $parts[] = $count.' '.Str::lower(Str::plural(FormType::from($type)->label(), $count)).' to review';
        }

        if ($changes > 0) {
            $parts[] = $changes.' officer change '.Str::plural('request', $changes).' to review';
        }

        $total = $accounts + $sdaoDocuments->sum() + $changes;

        if ($total > 0) {
            return [
                'tone' => 'orange',
                'icon' => 'user-plus',
                'title' => $total.' '.Str::plural('item', $total).' waiting for SDAO',
                'subtitle' => $this->sentence($parts).'.',
                'buttonLabel' => 'Open pending items',
                'href' => $this->pendingHref($accounts, $changes, $sdaoDocuments->keys()->all()),
            ];
        }

        $approved = DocumentTransition::query()
            ->where('action', TransitionAction::Completed->value)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'tone' => 'green',
            'icon' => 'check-circle',
            'title' => 'All clear',
            'subtitle' => 'Nothing is waiting on SDAO.'.($approved > 0
                ? ' '.$approved.' '.Str::plural('document', $approved).' approved this week.'
                : ''),
            'buttonLabel' => 'Open activity log',
            'href' => route('admin.activity.index'),
        ];
    }

    /** "a", "a and b", "a, b and c". */
    private function sentence(array $parts): string
    {
        $last = array_pop($parts);

        return $parts === [] ? (string) $last : implode(', ', $parts).' and '.$last;
    }

    /**
     * The one list the items live in when there is only one kind; the review
     * hub when they are spread over several.
     *
     * @param  array<int, string>  $documentTypes
     */
    private function pendingHref(int $accounts, int $changes, array $documentTypes): string
    {
        $kinds = ($accounts > 0 ? 1 : 0) + ($changes > 0 ? 1 : 0) + count($documentTypes);

        if ($kinds > 1) {
            return route('hubs.review');
        }

        return match (true) {
            $accounts > 0 => route('admin.pending-accounts.index'),
            $changes > 0 => route('admin.officer-change-requests.index'),
            default => route(FormType::from($documentTypes[0])->reviewIndexRouteName()),
        };
    }
}
