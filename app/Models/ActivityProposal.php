<?php

namespace App\Models;

use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Sdg;
use Database\Factories\ActivityProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $document_id
 * @property ProposalCalendarMode $calendar_mode
 * @property int|null $calendar_activity_id
 * @property string $title
 * @property ActivityNature|null $activity_nature
 * @property ActivityType|null $activity_type
 * @property array<int, string>|null $partner_organizations
 * @property Sdg|null $target_sdg
 * @property string|null $objectives
 * @property string|null $narrative
 * @property string|null $criteria_mechanics
 * @property string|null $program_flow
 * @property string|null $source_of_funding
 * @property string|null $expenses Legacy free-text expenses, kept only as a
 *                                 fallback for proposals submitted before expense_items existed — see
 *                                 App\Printing\ActivityProposalForm.
 * @property array<int, array{label: string, amount: string}>|null $expense_items
 * @property-read string|null $expenseItemsTotal Formatted ("1,234.56") grand
 *     total of expense_items, or null when there are no rows to sum.
 * @property float|null $proposed_budget
 * @property string|null $budget_source
 * @property int $form_step
 */
#[Fillable(['document_id', 'calendar_mode', 'calendar_activity_id', 'title', 'activity_nature', 'activity_type', 'partner_organizations', 'target_sdg', 'objectives', 'narrative', 'criteria_mechanics', 'program_flow', 'source_of_funding', 'expenses', 'expense_items', 'proposed_budget', 'budget_source', 'form_step'])]
class ActivityProposal extends Model
{
    /** @use HasFactory<ActivityProposalFactory> */
    use HasFactory;

    protected $casts = [
        'calendar_mode' => ProposalCalendarMode::class,
        'form_step' => 'integer',
        'activity_nature' => ActivityNature::class,
        'activity_type' => ActivityType::class,
        'partner_organizations' => 'array',
        'target_sdg' => Sdg::class,
        'expense_items' => 'array',
        'proposed_budget' => 'decimal:2',
    ];

    /**
     * Grand total of expense_items, summed in integer centavos (not floats)
     * to avoid drift artifacts like "20000.000000001" on the printed total.
     * Null when there are no rows — callers fall back to the legacy
     * `expenses` prose in that case, never print a misleading "0.00".
     */
    protected function expenseItemsTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->expense_items)) {
                    return null;
                }

                $centavos = array_sum(array_map(
                    fn (array $row) => (int) round(((float) $row['amount']) * 100),
                    $this->expense_items,
                ));

                return number_format($centavos / 100, 2);
            },
        );
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<CalendarActivity, $this> */
    public function calendarActivity(): BelongsTo
    {
        return $this->belongsTo(CalendarActivity::class);
    }

    /** @return HasMany<AfterActivityReport, $this> */
    public function afterActivityReports(): HasMany
    {
        return $this->hasMany(AfterActivityReport::class);
    }
}
