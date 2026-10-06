<?php

namespace App\Dashboard;

use App\Approval\SectionFlags;
use App\Attachments\AttachmentSlots;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Why documents come back, and how often. Everything here is scoped to
 * documents created in the current academic year (the same window the status
 * donut counts) and read from document_transitions, so it needs no schema.
 *
 * Percentages are never shown from tiny samples: below each minimum the card
 * says "Not enough data yet" instead of a misleading 100 percent.
 */
class ReturnAnalytics
{
    /** Returns with flagged sections needed before section shares mean anything. */
    public const int MIN_FLAGGED_RETURNS = 10;

    /** Submissions of one form type needed before its return rate is shown. */
    public const int MIN_SUBMISSIONS_PER_FORM_TYPE = 5;

    /** Approved documents needed before the average submissions figure is shown. */
    public const int MIN_APPROVED_FOR_AVERAGE = 5;

    private const int REASONS_LIMIT = 5;

    /**
     * @return array{reasons: array<string, mixed>, rates: array<string, mixed>}
     */
    public function forAcademicYear(CarbonInterface $yearStart, CarbonInterface $yearEnd): array
    {
        $documents = Document::query()
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->get(['id', 'form_type', 'status']);

        return [
            'reasons' => $this->reasons($documents),
            'rates' => $this->rates($documents),
        ];
    }

    /**
     * The most-flagged sections across returned documents. Each row's percent
     * is the share of flagged returns that flagged it; one return can flag
     * several sections, so the rows do not add up to 100.
     *
     * @param  Collection<int, Document>  $documents
     * @return array{sample: int, minimum: int, enough: bool, rows: array<int, array{label: string, count: int, percent: int}>, href: string}
     */
    private function reasons(Collection $documents): array
    {
        $formTypes = $documents->pluck('form_type', 'id');

        $returns = DocumentTransition::query()
            ->where('action', TransitionAction::Returned->value)
            ->whereIn('document_id', $documents->pluck('id'))
            ->whereNotNull('flagged_sections')
            ->get(['id', 'document_id', 'flagged_sections'])
            ->filter(fn (DocumentTransition $t) => ! empty($t->flagged_sections));

        $sample = $returns->count();
        $counts = [];

        foreach ($returns as $return) {
            /** @var FormType $formType */
            $formType = $formTypes[$return->document_id];

            foreach (array_unique($return->flagged_sections) as $key) {
                $label = $this->reasonLabel($formType, (string) $key);
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
        }

        $enough = $sample >= self::MIN_FLAGGED_RETURNS;

        return [
            'sample' => $sample,
            'minimum' => self::MIN_FLAGGED_RETURNS,
            'enough' => $enough,
            'rows' => $enough
                ? collect($counts)
                    ->map(fn (int $count, string $label) => [
                        'label' => $label,
                        'count' => $count,
                        'percent' => (int) round($count / $sample * 100),
                    ])
                    ->sortBy([['count', 'desc'], ['label', 'asc']])
                    ->take(self::REASONS_LIMIT)
                    ->values()
                    ->all()
                : [],
            'href' => route('admin.activity.index', ['action' => TransitionAction::Returned->value, 'date' => 'academic_year']),
        ];
    }

    /**
     * A section key as a person reads it: attachment slots as "Attachment:
     * {slot}", calendar rows as one label (their keys are positional), known
     * sections by their registry label, anything unrecognized humanized.
     */
    private function reasonLabel(FormType $formType, string $key): string
    {
        foreach (AttachmentSlots::for($formType) as $slot) {
            if ($slot->key === $key) {
                return 'Attachment: '.$slot->label;
            }
        }

        if ($formType === FormType::ActivityCalendar && preg_match('/^activity_\d+$/', $key)) {
            return 'Activity calendar row';
        }

        return SectionFlags::labelsFor($formType)[$key] ?? Str::headline($key);
    }

    /**
     * Share of submitted documents sent back at least once, per form type,
     * plus the average number of submissions before a document is approved
     * (1 + resubmissions, over approved documents).
     *
     * @param  Collection<int, Document>  $documents
     * @return array{minimum: int, rows: array<int, array<string, mixed>>, average: array<string, mixed>}
     */
    private function rates(Collection $documents): array
    {
        $submitted = $documents->where('status', '!=', DocumentStatus::Draft);

        $returnedIds = DocumentTransition::query()
            ->where('action', TransitionAction::Returned->value)
            ->whereIn('document_id', $submitted->pluck('id'))
            ->distinct()
            ->pluck('document_id');

        $rows = collect(FormType::cases())
            ->map(function (FormType $type) use ($submitted, $returnedIds) {
                $ofType = $submitted->where('form_type', $type);
                $count = $ofType->count();
                $returned = $ofType->whereIn('id', $returnedIds)->count();
                $enough = $count >= self::MIN_SUBMISSIONS_PER_FORM_TYPE;

                return [
                    'formType' => $type->value,
                    'label' => $type->label(),
                    'submitted' => $count,
                    'returned' => $returned,
                    'enough' => $enough,
                    'percent' => $enough ? (int) round($returned / $count * 100) : null,
                    'href' => route('admin.activity.index', ['form_type' => $type->value, 'action' => TransitionAction::Returned->value, 'date' => 'academic_year']),
                ];
            })
            ->sortBy([['enough', 'desc'], ['percent', 'desc'], ['label', 'asc']])
            ->values()
            ->all();

        return [
            'minimum' => self::MIN_SUBMISSIONS_PER_FORM_TYPE,
            'rows' => $rows,
            'average' => $this->averageSubmissions($documents),
        ];
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array{value: float|null, sample: int, minimum: int, enough: bool}
     */
    private function averageSubmissions(Collection $documents): array
    {
        $approvedIds = $documents->where('status', DocumentStatus::Approved)->pluck('id');

        $resubmissions = DocumentTransition::query()
            ->where('action', TransitionAction::Resubmitted->value)
            ->whereIn('document_id', $approvedIds)
            ->selectRaw('document_id, count(*) as aggregate')
            ->groupBy('document_id')
            ->pluck('aggregate', 'document_id');

        $sample = $approvedIds->count();
        $enough = $sample >= self::MIN_APPROVED_FOR_AVERAGE;

        return [
            'value' => $enough
                ? round($approvedIds->sum(fn (int $id) => 1 + (int) ($resubmissions[$id] ?? 0)) / $sample, 1)
                : null,
            'sample' => $sample,
            'minimum' => self::MIN_APPROVED_FOR_AVERAGE,
            'enough' => $enough,
        ];
    }
}
