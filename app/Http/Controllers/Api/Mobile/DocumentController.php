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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
}
