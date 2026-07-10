<?php

namespace App\Http\Requests\Admin;

use App\Enums\Term;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrentTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by the `access-admin` route middleware.
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term' => ['required', 'string', Rule::enum(Term::class)],
        ];
    }
}
