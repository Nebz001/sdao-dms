<?php

namespace App\Models;

use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\BudgetSource;
use App\Enums\ProposalCalendarMode;
use App\Enums\Sdg;
use Database\Factories\ActivityProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $document_id
 * @property ProposalCalendarMode $calendar_mode
 * @property int|null $calendar_activity_id
 * @property string $title
 * @property ActivityNature|null $activity_nature
 * @property string|null $activity_nature_other Free text when activity_nature
 *                                              is ActivityNature::Others (Group B item 5); meaningless otherwise.
 * @property ActivityType|null $activity_type
 * @property string|null $activity_type_other Free text when activity_type is
 *                                            ActivityType::Others (Group B item 5); meaningless otherwise.
 * @property array<int, string>|null $partner_organizations
 * @property Collection<int, Sdg>|null $target_sdg Multi-select (Group C item
 *                                                 1) — at least one goal in real student submissions (validation enforces
 *                                                 min:1), stored as a json array; same shape as
 *                                                 CalendarActivity::$sdg.
 * @property string|null $objectives Group E backlog — collapsed back into a
 *                                   single field (was briefly split into
 *                                   overall_goal/specific_objectives); both
 *                                   hint phrases now live as placeholder
 *                                   text on the one frontend field instead.
 * @property string|null $criteria_mechanics
 * @property string|null $program_flow
 * @property string|null $expenses Legacy free-text expenses, kept only as a
 *                                 fallback for proposals submitted before expense_items existed — see
 *                                 App\Printing\ActivityProposalForm.
 * @property array<int, array{material: string, quantity: string, unit_price: string}>|null $expense_items Group D item 3 — was {label, amount}.
 * @property array<int, string>|null $responsible_persons Group E backlog —
 *                                                        typed in directly by the submitting officer (President or Secretary),
 *                                                        not sourced from org membership: the system only ever tracks those two
 *                                                        as "members", too small a pool to stand in for who is actually
 *                                                        responsible for running an activity.
 * @property-read string|null $expenseItemsTotal Formatted ("1,234.56") grand
 *     total of expense_items, or null when there are no rows to sum.
 * @property-read string|null $activityNatureLabel activity_nature's label,
 *     with activity_nature_other appended when it's Others (Group B item 5).
 * @property-read string|null $activityTypeLabel activity_type's label, with
 *     activity_type_other appended when it's Others (Group B item 5).
 * @property float|null $proposed_budget
 * @property BudgetSource|null $budget_source Group C item 2 — RSO Fund / RSO
 *                                            Savings / External; was free text.
 * @property int $form_step
 */
#[Fillable(['document_id', 'calendar_mode', 'calendar_activity_id', 'title', 'activity_nature', 'activity_nature_other', 'activity_type', 'activity_type_other', 'partner_organizations', 'target_sdg', 'objectives', 'criteria_mechanics', 'program_flow', 'expenses', 'expense_items', 'responsible_persons', 'proposed_budget', 'budget_source', 'form_step'])]
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
        'target_sdg' => AsEnumCollection::class.':'.Sdg::class,
        'expense_items' => 'array',
        'responsible_persons' => 'array',
        'proposed_budget' => 'decimal:2',
        'budget_source' => BudgetSource::class,
    ];

    /**
     * Grand total of expense_items (quantity × unit_price per row, Group D
     * item 3 — was a flat `amount`), summed in integer centavos (not floats)
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
                    fn (array $row) => (int) round(((float) ($row['quantity'] ?? 0)) * ((float) ($row['unit_price'] ?? 0)) * 100),
                    $this->expense_items,
                ));

                return number_format($centavos / 100, 2);
            },
        );
    }

    /**
     * activity_nature's label, with the free-text detail appended when the
     * selection is Others (Group B item 5) — e.g. "Others — Cosplay meetup".
     */
    protected function activityNatureLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => self::otherAwareLabel($this->activity_nature, $this->activity_nature_other),
        );
    }

    /** Same as activityNatureLabel(), for activity_type. */
    protected function activityTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => self::otherAwareLabel($this->activity_type, $this->activity_type_other),
        );
    }

    private static function otherAwareLabel(ActivityNature|ActivityType|null $case, ?string $other): ?string
    {
        if ($case === null) {
            return null;
        }

        $isOthers = ($case instanceof ActivityNature && $case === ActivityNature::Others)
            || ($case instanceof ActivityType && $case === ActivityType::Others);

        return $isOthers && $other !== null && $other !== ''
            ? "{$case->label()} — {$other}"
            : $case->label();
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
