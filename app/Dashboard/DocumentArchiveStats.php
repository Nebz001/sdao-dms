<?php

namespace App\Dashboard;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Everything the Document Archive page counts, over the WHOLE archive scope
 * (every Approved and Rejected document). The page's filters never narrow
 * these figures, so the cards stay put while someone filters the table.
 *
 * "Decided on" is the time of the document's final approve or reject
 * transition (the last `document_transitions` row that moved it into a
 * terminal status), never `documents.updated_at`. A document with no such
 * transition (rows written straight to the table, with no history) falls
 * back to `updated_at` so it still has a date.
 *
 * Week buckets are built in PHP (Monday-start, same convention as
 * PendingAccountStats::weeklyCounts()) so the sqlite test suite runs the same
 * code as Postgres.
 */
class DocumentArchiveStats
{
    /** The two terminal statuses: everything the archive lists. */
    public const array ARCHIVED_STATUSES = [
        DocumentStatus::Approved->value,
        DocumentStatus::Rejected->value,
    ];

    /** How many weeks the "Decided per week" chart shows, the running week included. */
    public const int WEEKS = 8;

    /** Plain short names for the "By form type" rows. */
    private const array SHORT_LABELS = [
        'organization_registration' => 'Registration',
        'organization_renewal' => 'Renewal',
        'activity_calendar' => 'Activity Calendar',
        'activity_proposal' => 'Activity Proposal',
        'after_activity_report' => 'After-Activity Report',
    ];

    /**
     * Raw SQL for a document's decision time. Plain coalesce and a correlated
     * subquery, so it runs on both Postgres and sqlite.
     */
    public static function decidedAtSql(): string
    {
        return '(coalesce((select max(document_transitions.created_at) from document_transitions'
            .' where document_transitions.document_id = documents.id'
            .' and document_transitions.to_status in (?, ?)), documents.updated_at))';
    }

    /** @return array<int, string> */
    private static function decidedAtBindings(): array
    {
        return self::ARCHIVED_STATUSES;
    }

    /**
     * Archived documents with a `decided_at` column.
     *
     * @return Builder<Document>
     */
    public static function archivedWithDecidedAt(): Builder
    {
        return Document::query()
            ->select('documents.*')
            ->selectRaw(self::decidedAtSql().' as decided_at', self::decidedAtBindings())
            ->whereIn('documents.status', self::ARCHIVED_STATUSES);
    }

    /**
     * @return array{
     *     total: array{total: int, approved: int, rejected: int},
     *     byFormType: array<int, array{form_type: string, label: string, count: int}>,
     *     perWeek: array{thisWeek: int, weeks: array<int, int>},
     *     topOrganization: array{id: int, name: string, approved: int, rejected: int, total: int}|null,
     * }
     */
    public function summary(?CarbonInterface $now = null): array
    {
        $now ??= Date::now();

        return [
            'total' => $this->totals(),
            'byFormType' => $this->byFormType(),
            'perWeek' => $this->perWeek($now),
            'topOrganization' => $this->topOrganization(),
        ];
    }

    /** @return array{total: int, approved: int, rejected: int} */
    private function totals(): array
    {
        $counts = Document::query()
            ->whereIn('status', self::ARCHIVED_STATUSES)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $approved = (int) ($counts[DocumentStatus::Approved->value] ?? 0);
        $rejected = (int) ($counts[DocumentStatus::Rejected->value] ?? 0);

        return ['total' => $approved + $rejected, 'approved' => $approved, 'rejected' => $rejected];
    }

    /**
     * All five form types, highest count first, zero counts included. Equal
     * counts keep the order the form types are declared in FormType.
     *
     * @return array<int, array{form_type: string, label: string, count: int}>
     */
    private function byFormType(): array
    {
        $counts = Document::query()
            ->whereIn('status', self::ARCHIVED_STATUSES)
            ->selectRaw('form_type, count(*) as aggregate')
            ->groupBy('form_type')
            ->pluck('aggregate', 'form_type');

        return collect(FormType::cases())
            ->map(fn (FormType $type, int $order) => [
                'form_type' => $type->value,
                'label' => self::SHORT_LABELS[$type->value],
                'count' => (int) ($counts[$type->value] ?? 0),
                'order' => $order,
            ])
            ->sort(fn (array $a, array $b) => [$b['count'], $a['order']] <=> [$a['count'], $b['order']])
            ->map(fn (array $row) => ['form_type' => $row['form_type'], 'label' => $row['label'], 'count' => $row['count']])
            ->values()
            ->all();
    }

    /**
     * Documents decided in each of the last WEEKS Monday-start weeks, oldest
     * first; the last entry is the running week.
     *
     * @return array{thisWeek: int, weeks: array<int, int>}
     */
    private function perWeek(CarbonInterface $now): array
    {
        $firstWeekStart = $now->copy()->startOfWeek()->subWeeks(self::WEEKS - 1);

        /** @var Collection<int, CarbonInterface> $decidedAt */
        $decidedAt = Document::query()
            ->whereIn('documents.status', self::ARCHIVED_STATUSES)
            ->whereRaw(self::decidedAtSql().' >= ?', [...self::decidedAtBindings(), $firstWeekStart])
            ->selectRaw(self::decidedAtSql().' as decided_at', self::decidedAtBindings())
            ->pluck('decided_at')
            ->map(fn (string $value) => Date::parse($value));

        $weeks = [];

        for ($i = 0; $i < self::WEEKS; $i++) {
            $start = $firstWeekStart->copy()->addWeeks($i);
            $end = $start->copy()->addWeek();

            $weeks[] = $decidedAt
                ->filter(fn (CarbonInterface $t) => $t->greaterThanOrEqualTo($start) && $t->lessThan($end))
                ->count();
        }

        return ['thisWeek' => $weeks[self::WEEKS - 1], 'weeks' => $weeks];
    }

    /**
     * The organization with the most decided documents. Tie rule: the
     * organization whose name comes first alphabetically (case-insensitive),
     * then the lower id, so the same organization always wins a tie.
     *
     * @return array{id: int, name: string, approved: int, rejected: int, total: int}|null
     */
    private function topOrganization(): ?array
    {
        $rows = Document::query()
            ->join('organizations', 'organizations.id', '=', 'documents.organization_id')
            ->whereIn('documents.status', self::ARCHIVED_STATUSES)
            ->selectRaw('organizations.id as organization_id, organizations.name as organization_name, documents.status as status, count(*) as aggregate')
            ->groupBy('organizations.id', 'organizations.name', 'documents.status')
            ->get();

        $top = $rows
            ->groupBy('organization_id')
            ->map(function (Collection $group) {
                $approved = (int) $group->firstWhere('status', DocumentStatus::Approved)?->aggregate;
                $rejected = (int) $group->firstWhere('status', DocumentStatus::Rejected)?->aggregate;

                return [
                    'id' => (int) $group->first()->organization_id,
                    'name' => (string) $group->first()->organization_name,
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'total' => $approved + $rejected,
                ];
            })
            ->sort(fn (array $a, array $b) => [$b['total'], mb_strtolower($a['name']), $a['id']] <=> [$a['total'], mb_strtolower($b['name']), $b['id']])
            ->first();

        return $top;
    }
}
