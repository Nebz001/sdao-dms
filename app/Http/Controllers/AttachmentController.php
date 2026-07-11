<?php

namespace App\Http\Controllers;

use App\Attachments\AttachmentSlot;
use App\Attachments\AttachmentSlots;
use App\Attachments\AttachmentStorage;
use App\Enums\DocumentStatus;
use App\Http\Requests\Attachments\StoreAttachmentRequest;
use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mode B (Phase 2 item 8) — attach-to-existing-document upload, immediate
 * and independent of the parent form's own Submit/Update request. Currently
 * only used by Activity Proposal's one optional slot (Resume of Resource
 * Person(s)), reachable from step-two.tsx while the document is still Draft
 * or Returned. Also hosts the generic download route shared by every form
 * type's Mode-A attachments.
 */
class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request, AttachmentStorage $attachmentStorage): JsonResponse
    {
        $document = Document::findOrFail($request->integer('document_id'));

        $this->authorizeMutation($document);

        $slot = $this->resolveSlot($document, $request->string('slot_key')->toString());

        $attachment = $attachmentStorage->store(
            document: $document,
            slotKey: $slot->key,
            file: $request->file('file'),
            actor: Auth::user(),
            multiple: $slot->multiple,
        );

        return response()->json([
            'id' => $attachment->id,
            'original_filename' => $attachment->original_filename,
            'download_url' => route('attachments.download', $attachment),
        ], 201);
    }

    public function destroy(DocumentAttachment $attachment, AttachmentStorage $attachmentStorage): HttpResponse
    {
        $this->authorizeMutation($attachment->document);

        $attachmentStorage->delete($attachment);

        return response()->noContent();
    }

    public function download(DocumentAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->document);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_filename);
    }

    /**
     * Only the original submitter, and only while the document is still
     * editable (Draft — step-2 in progress — or Returned — resubmitting),
     * same as the Draft/Returned checks already used inline elsewhere for
     * this document (e.g. ActivityProposalController::draft()/submit()).
     */
    private function authorizeMutation(Document $document): void
    {
        $isEditable = in_array($document->status, [DocumentStatus::Draft, DocumentStatus::Returned], true);

        if ($document->submitted_by !== Auth::id() || ! $isEditable) {
            abort(403);
        }
    }

    /**
     * @throws ValidationException
     */
    private function resolveSlot(Document $document, string $slotKey): AttachmentSlot
    {
        $slot = collect(AttachmentSlots::for($document->form_type))->first(fn (AttachmentSlot $s) => $s->key === $slotKey);

        if ($slot === null) {
            throw ValidationException::withMessages([
                'slot_key' => 'Unknown attachment slot for this document type.',
            ]);
        }

        return $slot;
    }
}
