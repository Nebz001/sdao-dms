<?php

namespace App\Attachments;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Generic attachment storage (Phase 2 item 8), shared by every form type's
 * write path (Mode A — bundled with Store/Update — and Mode B — the
 * standalone attach-to-existing-document endpoint). Local disk for now,
 * Laravel as the sole write path — see plan notes on switching to a cloud
 * disk later.
 */
class AttachmentStorage
{
    /**
     * Store one uploaded file into the given slot. If the slot isn't
     * `multiple`, any existing file(s) in that slot are deleted first
     * (replace semantics) — a multi-file slot simply accumulates.
     */
    public function store(Document $document, string $slotKey, UploadedFile $file, ?User $actor, bool $multiple): DocumentAttachment
    {
        if (! $multiple) {
            $document->attachments()
                ->where('slot_key', $slotKey)
                ->get()
                ->each(fn (DocumentAttachment $existing) => $this->delete($existing));
        }

        $path = $file->store("attachments/{$document->form_type->value}/{$document->id}", 'local');

        return $document->attachments()->create([
            'slot_key' => $slotKey,
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => 'local',
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $actor?->id,
        ]);
    }

    /**
     * Store every uploaded slot file for a Mode-A (bundled) write. Looks up
     * each slot's `multiple` flag from the registry so callers don't need to
     * track it themselves.
     *
     * @param  array<string, UploadedFile|array<int, UploadedFile>>  $filesBySlot
     */
    public function storeMany(Document $document, array $filesBySlot, ?User $actor): void
    {
        $slotsByKey = collect(AttachmentSlots::for($document->form_type))->keyBy('key');

        foreach ($filesBySlot as $slotKey => $files) {
            $slot = $slotsByKey->get($slotKey);

            if ($slot === null) {
                continue;
            }

            foreach (Arr::wrap($files) as $file) {
                $this->store($document, $slotKey, $file, $actor, $slot->multiple);
            }
        }
    }

    /**
     * Hard re-check that every required slot for this document's form type is
     * filled — the same defensive pattern already used for venue-conflict and
     * adviser-exclusivity re-checks (FormRequest validation is the soft/first
     * layer). Must be called AFTER storeMany() within the same DB
     * transaction: the just-inserted rows are already visible to this query,
     * so no separate "staged keys" tracking is needed.
     *
     * @throws ValidationException
     */
    public function assertRequiredSlotsFilled(Document $document): void
    {
        $existingKeys = $document->attachments()->pluck('slot_key')->unique();

        $missing = collect(AttachmentSlots::for($document->form_type))
            ->filter(fn (AttachmentSlot $slot) => $slot->required && ! $existingKeys->contains($slot->key))
            ->pluck('label');

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'attachments' => 'Missing required attachment(s): '.$missing->implode(', ').'.',
            ]);
        }
    }

    public function delete(DocumentAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }
}
