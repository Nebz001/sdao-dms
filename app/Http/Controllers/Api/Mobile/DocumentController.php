<?php

namespace App\Http\Controllers\Api\Mobile;

use App\ActivityProposals\ProposalReference;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ProposalResource;
use App\Http\Resources\Mobile\ProposalSummaryResource;
use App\Http\Resources\Mobile\ProposalTimestamps;
use App\Identity\RoleDirectory;
use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const array DETAIL_RELATIONS = [
        'organization',
        'submitter',
        'activityProposal.calendarActivity',
        'workflowTemplate.steps',
        'transitions.actor',
        'stepApprovals',
        'attachments',
    ];

    /**
     * Every non-terminal Activity Proposal the authenticated approver may
     * at least VIEW (their current step, a step they already acted on, or
     * any step the chain has already reached — DocumentPolicy::reviewView()),
     * actionable ones first, then oldest submitted first.
     *
     * Bulk eager-loads once for the whole set and wraps approver-seat
     * resolution in RoleDirectory::remembering(), so DocumentPolicy's own
     * relation-reuse fast path (commit 3) and the seat-lookup memo do the
     * N+1 avoidance — this method issues no per-document queries itself.
     */
    public function queue(Request $request): JsonResponse
    {
        $user = $request->user();

        $viewable = app(RoleDirectory::class)->remembering(
            fn () => Document::query()
                ->where('form_type', FormType::ActivityProposal->value)
                ->whereIn('status', [DocumentStatus::InReview->value, DocumentStatus::Returned->value])
                ->with(self::DETAIL_RELATIONS)
                ->get()
                ->filter(fn (Document $d) => Gate::forUser($user)->allows('reviewView', $d))
                ->sortBy(fn (Document $d) => [
                    Gate::forUser($user)->allows('review', $d) ? 0 : 1,
                    ProposalTimestamps::submittedAt($d)?->timestamp ?? 0,
                ])
                ->values(),
        );

        return response()->json(
            $viewable->map(fn (Document $d) => new ProposalSummaryResource($d, $user))->all(),
        );
    }

    public function show(Request $request, string $proposalReference): ProposalResource
    {
        $document = ProposalReference::resolve($proposalReference);

        if ($document === null) {
            abort(404, 'Activity Proposal not found.');
        }

        $document->load(self::DETAIL_RELATIONS);

        Gate::forUser($request->user())->authorize('reviewView', $document);

        return new ProposalResource($document, $request->user());
    }

    /**
     * Streams one attachment's raw file bytes — the only mobile endpoint
     * that doesn't return JSON on success. Same reference resolution and
     * `reviewView` authorization as show() above; the attachment must
     * belong to THIS document (query-scoped by document_id, not just
     * looked up by its own id) or this 404s exactly like an unknown
     * attachment would, rather than leaking whether the id exists on some
     * other document. Reuses the same disk/path/download mechanism as the
     * web app's own AttachmentController::download() — no separate storage
     * scheme for mobile.
     */
    public function downloadAttachment(Request $request, string $proposalReference, int $attachmentId): StreamedResponse
    {
        $document = ProposalReference::resolve($proposalReference);

        if ($document === null) {
            abort(404, 'Activity Proposal not found.');
        }

        Gate::forUser($request->user())->authorize('reviewView', $document);

        $attachment = DocumentAttachment::query()
            ->where('id', $attachmentId)
            ->where('document_id', $document->id)
            ->first();

        if ($attachment === null) {
            abort(404, 'Attachment not found.');
        }

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'The file for this attachment could not be found in storage.');
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_filename,
            ['Content-Type' => $attachment->mime_type],
        );
    }
}
