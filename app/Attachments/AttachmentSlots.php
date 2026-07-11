<?php

namespace App\Attachments;

use App\Enums\FormType;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Static registry of attachment slots per form type (Phase 2 item 8),
 * sourced verbatim from the client's real physical/template forms (sdao.md).
 * One generic pattern reused by every form type — not four one-off
 * implementations.
 */
class AttachmentSlots
{
    /** Document-style slots (letters, forms, statements): PDF or a scanned/photographed image. */
    private const array DOCUMENT_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    private const int DOCUMENT_MAX_KB = 10240; // 10 MB

    /** Photo slots: images only. */
    private const array PHOTO_MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    private const int PHOTO_MAX_KB = 5120; // 5 MB

    private const int PHOTO_MAX_COUNT = 10;

    /**
     * Validation rules for a single document-style file upload — shared by
     * validationRules() (Mode A, bundled) and Mode B's standalone
     * StoreAttachmentRequest, so the mime/size limits live in exactly one
     * place.
     *
     * @return array<int, mixed>
     */
    public static function documentFileRules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', self::DOCUMENT_MIMES),
            'max:'.self::DOCUMENT_MAX_KB,
        ];
    }

    /**
     * @return array<int, AttachmentSlot>
     */
    public static function for(FormType $formType): array
    {
        return match ($formType) {
            FormType::OrganizationRegistration => [
                new AttachmentSlot('letter_of_intent', 'Letter of Intent', required: true),
                new AttachmentSlot('application_form', 'Application Form', required: true),
                new AttachmentSlot('by_laws', 'By-Laws', required: true),
                new AttachmentSlot('officers_list', 'Updated List of Officers/Founders', required: true),
                new AttachmentSlot('dean_endorsement_letter', 'Letter from College Dean endorsing the Faculty Adviser', required: true),
                new AttachmentSlot('proposed_projects_budget', 'List of Proposed Projects with Budget', required: true),
            ],
            FormType::OrganizationRenewal => [
                ...self::for(FormType::OrganizationRegistration),
                new AttachmentSlot('past_projects_list', 'List of Past Projects', required: true),
                new AttachmentSlot('financial_statement', 'Financial Statement', required: true),
                new AttachmentSlot('evaluation_summary', 'Summary of Evaluation', required: true),
            ],
            FormType::AfterActivityReport => [
                new AttachmentSlot('photos', 'Photos', required: true, multiple: true),
                new AttachmentSlot('evaluation_form', 'Sample Evaluation Form', required: true),
                new AttachmentSlot('attendance_sheet', 'Attendance Sheet', required: true),
            ],
            FormType::ActivityProposal => [
                new AttachmentSlot('resume_of_resource_person', 'Resume of Resource Person(s)', required: false),
            ],
            FormType::ActivityCalendar => [],
        };
    }

    /**
     * Merged into a FormRequest's own rules(). When $requiredAtWrite is false
     * (Update/resubmit), every slot becomes nullable regardless of its own
     * `required` flag — AttachmentStorage::assertRequiredSlotsFilled() is the
     * real completeness gate there, checking persisted rows + anything newly
     * attached in the same request, so an untouched slot from a prior
     * submission is never forced to be re-uploaded.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(FormType $formType, bool $requiredAtWrite): array
    {
        $rules = [];

        foreach (self::for($formType) as $slot) {
            $key = "attachments.{$slot->key}";
            $isRequired = $requiredAtWrite && $slot->required;

            if ($slot->multiple) {
                $rules[$key] = [$isRequired ? 'required' : 'nullable', 'array', 'max:'.self::PHOTO_MAX_COUNT];
                $rules["{$key}.*"] = ['file', 'mimes:'.implode(',', self::PHOTO_MIMES), 'max:'.self::PHOTO_MAX_KB];
            } else {
                $rules[$key] = self::documentFileRules($isRequired);
            }
        }

        return $rules;
    }

    /**
     * Human-readable validation attribute labels for every slot, merged into
     * a FormRequest's own attributes() so error messages read "The Letter of
     * Intent field is required" rather than "The attachments.letter_of_intent
     * field is required."
     *
     * @return array<string, string>
     */
    public static function validationAttributes(FormType $formType): array
    {
        $attributes = [];

        foreach (self::for($formType) as $slot) {
            $attributes["attachments.{$slot->key}"] = $slot->label;

            if ($slot->multiple) {
                $attributes["attachments.{$slot->key}.*"] = $slot->label;
            }
        }

        return $attributes;
    }

    /**
     * Pulls UploadedFile(s) out of the request per slot, keyed by slot_key —
     * only slots actually present in the request are included (so an
     * untouched slot on Update doesn't get passed through as null).
     *
     * @return array<string, UploadedFile|array<int, UploadedFile>>
     */
    public static function extractUploadedFiles(Request $request, FormType $formType): array
    {
        $files = [];

        foreach (self::for($formType) as $slot) {
            $key = "attachments.{$slot->key}";

            if (! $request->hasFile($key)) {
                continue;
            }

            $files[$slot->key] = $request->file($key);
        }

        return $files;
    }

    /**
     * Plain-array slot descriptions for a create page (no Document exists
     * yet, so there are no existing files to present) — see
     * presentForDocument() for the show/edit variant. Includes an `accept`
     * hint (HTML input accept attribute format) derived from the same
     * mime-type constants as validationRules(), so the frontend never
     * hardcodes them separately.
     *
     * @return array<int, array{key: string, label: string, required: bool, multiple: bool, accept: string}>
     */
    public static function slotsFor(FormType $formType): array
    {
        return collect(self::for($formType))->map(fn (AttachmentSlot $slot) => [
            'key' => $slot->key,
            'label' => $slot->label,
            'required' => $slot->required,
            'multiple' => $slot->multiple,
            'accept' => self::acceptAttributeFor($slot),
        ])->values()->all();
    }

    private static function acceptAttributeFor(AttachmentSlot $slot): string
    {
        $mimes = $slot->multiple ? self::PHOTO_MIMES : self::DOCUMENT_MIMES;

        return collect($mimes)->map(fn ($ext) => ".{$ext}")->implode(',');
    }

    /**
     * Shared Inertia-props builder for show/edit/review-show pages across
     * every form type — one rendering helper instead of four hand-rolled
     * blocks. Requires `$document->attachments` to already be loaded
     * (eager-load via `document.attachments`).
     *
     * @return array{
     *     slots: array<int, array{key: string, label: string, required: bool, multiple: bool}>,
     *     files: array<string, array<int, array{id: int, original_filename: string, download_url: string}>>,
     * }
     */
    public static function presentForDocument(Document $document): array
    {
        $files = $document->attachments
            ->groupBy('slot_key')
            ->map(fn ($group) => $group->map(fn ($a) => [
                'id' => $a->id,
                'original_filename' => $a->original_filename,
                'download_url' => route('attachments.download', $a),
            ])->values()->all())
            ->all();

        return ['slots' => self::slotsFor($document->form_type), 'files' => $files];
    }
}
