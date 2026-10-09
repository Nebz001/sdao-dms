<?php

namespace App\Models;

use App\Enums\FormType;
use App\Enums\ProposalVariant;
use Database\Factories\WorkflowTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property FormType $form_type
 * @property ProposalVariant|null $variant
 * @property string $name
 * @property Carbon|null $retired_at
 */
#[Fillable(['form_type', 'variant', 'name'])]
class WorkflowTemplate extends Model
{
    /** @use HasFactory<WorkflowTemplateFactory> */
    use HasFactory;

    protected $casts = [
        'form_type' => FormType::class,
        'variant' => ProposalVariant::class,
        'retired_at' => 'datetime',
    ];

    /**
     * Templates new submissions may use. A retired template only keeps the
     * documents already routed through it readable.
     *
     * @param  Builder<WorkflowTemplate>  $query
     * @return Builder<WorkflowTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('retired_at'));
    }

    /** @return HasMany<WorkflowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('position');
    }
}
