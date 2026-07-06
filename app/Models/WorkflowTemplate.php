<?php

namespace App\Models;

use App\Enums\FormType;
use App\Enums\ProposalVariant;
use Database\Factories\WorkflowTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property FormType $form_type
 * @property ProposalVariant|null $variant
 * @property string $name
 */
#[Fillable(['form_type', 'variant', 'name'])]
class WorkflowTemplate extends Model
{
    /** @use HasFactory<WorkflowTemplateFactory> */
    use HasFactory;

    protected $casts = [
        'form_type' => FormType::class,
        'variant' => ProposalVariant::class,
    ];

    /** @return HasMany<WorkflowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('position');
    }
}
