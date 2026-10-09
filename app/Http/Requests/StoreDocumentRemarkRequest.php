<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreDocumentRemarkRequest extends FormRequest
{
    /** The longest a remark can be. Mirrored by the textarea's maxLength. */
    public const MAX_LENGTH = 1000;

    public function authorize(): bool
    {
        // Checked here as well as in the controller so a user who may not
        // remark gets a 403, not a validation error about their body.
        return Gate::forUser($this->user())->allows('remark', $this->route('document'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.self::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Write a remark before adding it.',
            'body.max' => 'A remark can be at most '.self::MAX_LENGTH.' characters.',
        ];
    }
}
