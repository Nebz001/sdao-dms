<?php

namespace App\Http\Controllers;

use App\Attachments\AttachmentSlot;
use App\Attachments\AttachmentSlots;
use App\Attachments\AttachmentStorage;
use App\Enums\DocumentStatus;
use App\Http\Requests\Attachments\StoreAttachmentRequest;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Organizations\OrganizationMembershipService;
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
    /** Mime types preview() may serve inline. Matches what uploads accept: PDFs and jpg/png/webp images. */
    private const array INLINE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly OrganizationMembershipService $membershipService,
    ) {}

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
            'preview_url' => route('attachments.preview', $attachment),
            'size' => $attachment->size,
            'mime_type' => $attachment->mime_type,
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
     * Serves the file inline so the browser (or the in-page preview) renders
     * it, instead of forcing a download. Same access check as download().
     *
     * Only types a browser renders safely are served inline — PDFs and raster
     * images. Anything else is sent as an attachment even from here, so a
     * stored file can never be rendered as a page on the app's origin.
     */
    public function preview(DocumentAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->document);

        $inline = in_array($attachment->mime_type, self::INLINE_MIME_TYPES, true);

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_filename,
            [
                'Content-Type' => $inline ? $attachment->mime_type : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }

    /**
     * Only an active officer of the document's org (or the submitter, for
     * the founding-registration edge case — see canActOnDocument()), and
     * only while the document is still editable (Draft — step-2 in progress
     * — or Returned — resubmitting), same as the Draft/Returned checks
     * already used inline elsewhere for this document (e.g.
     * ActivityProposalController::draft()/submit()).
     */
    private function authorizeMutation(Document $document): void
    {
        $isEditable = in_array($document->status, [DocumentStatus::Draft, DocumentStatus::Returned], true);

        if (! $this->membershipService->canActOnDocument(Auth::user(), $document) || ! $isEditable) {
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
