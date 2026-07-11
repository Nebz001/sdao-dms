<?php

namespace App\Http\Requests\Review;

use App\Approval\SectionFlags;
use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        $rules = [
            'comment' => [$commentRequired ? 'required' : 'nullable', 'string', 'max:2000'],
        ];

        // Phase 2 item 9 — section flags are return-only metadata (purely
        // informational; see App\Approval\SectionFlags). Reject is terminal
        // and never carries them — no rule is added for it, so an unexpected
        // `sections` key on a reject request is simply ignored, same as any
        // other unvalidated input.
        if ($action === 'return') {
            /** @var Document $document */
            $document = $this->route('document');
            $validKeys = SectionFlags::validKeysFor($document->form_type, $document);

            $rules['sections'] = ['nullable', 'array'];
            $rules['sections.*'] = ['string', Rule::in($validKeys)];
        }

        return $rules;
    }
}
