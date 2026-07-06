<?php

namespace App\Http\Requests\Review;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy.
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var string $action */
        $action = $this->route()->getActionMethod();

        $commentRequired = in_array($action, ['reject', 'return'], strict: true);

        return [
            'comment' => [$commentRequired ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }
}
