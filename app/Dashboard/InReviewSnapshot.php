<?php

namespace App\Dashboard;

use App\Approval\StepApproverResolver;
use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Identity\RoleDirectory;
use App\Models\Document;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * The single source for every "waiting on an approver" figure on the admin
 * dashboard and the Stuck Documents page: the tile counts, the per-approver
 * table, the who-are-we-waiting-on split and the oldest-in-review list all
 * read from the same rows, so they cannot disagree.
 *
 * Idle time is always measured from Document::latestTransition(), never
 * documents.updated_at (see that relation's docblock). An SDAO member's
 * partial approval writes a transition, so it resets the idle clock too.
 *
 * Cost: one eager-loaded document query plus resolver lookups memoized by
 * RoleDirectory::remembering(), the same pattern as ApproverQueue::pendingFor().
 */
class InReviewSnapshot
{
    /** Age tiers on the admin dashboard: fresh under 3 days, stale over 7. */
    public const int FRESH_UNDER_DAYS = 3;

    public const int STALE_OVER_DAYS = 7;

    /** The "N waiting over 5 days" hint on the Awaiting SDAO review tile. */
    public const int SDAO_FOLLOW_UP_DAYS = 5;

    /** @var Collection<int, StuckDocument>|null */
    private ?Collection $rows = null;

    public function __construct(
        private readonly StepApproverResolver $resolver,
        private readonly RoleDirectory $directory,
    ) {}

    /**
     * 'stale' over 7 days, 'aging' from 3 to 7, 'fresh' under 3.
     */
    public static function tierFor(int $idleDays): string
    {
        if ($idleDays > self::STALE_OVER_DAYS) {
            return 'stale';
        }

        return $idleDays >= self::FRESH_UNDER_DAYS ? 'aging' : 'fresh';
    }

    /**
     * Whole days since the document's last real action.
     */
    public static function idleDays(Document $document): int
    {
        return (int) ($document->latestTransition?->created_at ?? $document->created_at)->diffInDays(now());
    }

    /**
     * Every InReview document, oldest idle first.
     *
     * @return Collection<int, StuckDocument>
     */
    public function rows(): Collection
    {
        return $this->rows ??= $this->build();
    }

    /**
     * Rows grouped by the approver whose step they are on, oldest first.
     *
     * @return Collection<int, array{key: string, name: string, line: string, waiting: int, oldest: int, median: int, tier: string}>
     */
    public function groups(): Collection
    {
        return $this->rows()
            ->groupBy('approverKey')
            ->map(function (Collection $group, string $key) {
                /** @var StuckDocument $first */
                $first = $group->first();
                $oldest = (int) $group->max('idleDays');

                return [
                    'key' => $key,
                    'name' => $first->approverName,
                    'line' => $first->approverLine,
                    'waiting' => $group->count(),
                    'oldest' => $oldest,
                    'median' => self::median($group->pluck('idleDays')->all()),
                    'tier' => self::tierFor($oldest),
                ];
            })
            ->sortBy([['oldest', 'desc'], ['waiting', 'desc']])
            ->values();
    }

    /**
     * @param  array<int, int>  $values
     */
    public static function median(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? $values[$middle]
            : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    /**
     * @return Collection<int, StuckDocument>
     */
    private function build(): Collection
    {
        /** @var Collection<int, StuckDocument> $rows */
        $rows = $this->directory->remembering(fn () => Document::query()
            ->with([
                ...DocumentDisplayTitle::relations(),
                'organization.school',
                'organization.program',
                'workflowTemplate.steps',
                'latestTransition',
            ])
            ->where('status', DocumentStatus::InReview->value)
            ->get()
            ->map(fn (Document $document) => $this->describe($document))
            ->filter()
            ->sortByDesc('idleDays')
            ->values());

        return $rows;
    }

    private function describe(Document $document): ?StuckDocument
    {
        $step = $document->workflowTemplate?->steps->firstWhere('position', $document->current_step_position);

        if ($step === null) {
            return null;
        }

        $idleDays = self::idleDays($document);

        if ($step->role === Role::SdaoMember) {
            return $this->row($document, $step->role, 'sdao', 'SDAO Office', 'SDAO review step', $idleDays);
        }

        $scope = $this->scopeName($step->role, $document->organization);
        $line = $scope === null ? $step->role->label() : $step->role->label().', '.$scope;

        try {
            $approver = $this->resolver->approversFor($step, $document)->first();
        } catch (\Throwable $e) {
            Log::error('Approver resolution failed while building the in-review snapshot', ['exception' => $e->getMessage()]);
            $approver = null;
        }

        if ($approver === null) {
            return $this->row($document, $step->role, 'unassigned:'.$step->role->value.':'.($scope ?? ''), 'Unassigned', $line, $idleDays);
        }

        return $this->row($document, $step->role, 'user:'.$approver->id, $approver->name, $line, $idleDays);
    }

    private function row(Document $document, Role $role, string $key, string $name, string $line, int $idleDays): StuckDocument
    {
        return new StuckDocument($document, $role, $key, $name, $line, $idleDays, self::tierFor($idleDays));
    }

    /**
     * The seat's scope as a real name: the org for an adviser, the program
     * for a chair, the school for a dean or principal. No abbreviation column
     * exists, so the full name is shown.
     */
    private function scopeName(Role $role, Organization $organization): ?string
    {
        return match ($role) {
            Role::Adviser => $organization->name,
            Role::ProgramChair => $organization->program?->name,
            Role::Dean, Role::Principal => $organization->school?->name,
            default => null,
        };
    }
}
