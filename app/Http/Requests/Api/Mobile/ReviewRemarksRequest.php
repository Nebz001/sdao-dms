<?php

namespace App\Http\Requests\Api\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by request-revision and reject — the contract requires `remarks`
 * on both, with the same 2000-char limit the web's own comment field uses
 * (App\Http\Requests\Review\ReviewActionRequest).
 */
class ReviewRemarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'remarks' => ['required', 'string', 'max:2000'],
        ];
    }
}
