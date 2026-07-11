<?php

namespace App\Http\Requests\Attachments;

use App\Attachments\AttachmentSlots;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mode B (Phase 2 item 8) — attach-to-existing-document upload, independent
 * of the parent form's own Submit/Update. Currently only used by Activity
 * Proposal's one optional slot (Resume of Resource Person(s)), which is
 * document-style (PDF or scanned/photographed image).
 */
class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in the controller (submitter + Draft/Returned check).
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'slot_key' => ['required', 'string'],
            'file' => AttachmentSlots::documentFileRules(required: true),
        ];
    }
}
