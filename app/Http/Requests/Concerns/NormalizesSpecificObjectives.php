<?php

namespace App\Http\Requests\Concerns;

use App\Support\SpecificObjectives;

/**
 * For the requests that take Specific Objectives: its line breaks are made
 * plain "\n" before validation, so the length cap counts what the page counted
 * and the stored text is the same whether it came from the autosave or a form
 * post. Call it from the request's own prepareForValidation().
 */
trait NormalizesSpecificObjectives
{
    protected function normalizeSpecificObjectives(): void
    {
        $value = $this->input('specific_objectives');

        if (is_string($value)) {
            $this->merge(['specific_objectives' => SpecificObjectives::normalize($value)]);
        }
    }
}
