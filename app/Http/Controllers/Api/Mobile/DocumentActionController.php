<?php

namespace App\Http\Controllers\Api\Mobile;

use App\ActivityProposals\Exceptions\ProposalVenueConflictException;
use App\ActivityProposals\ProposalReference;
use App\ActivityProposals\ReviewActivityProposal;
use App\ActivityProposals\RevisionSectionParser;
use App\Approval\Exceptions\DuplicateApprovalException;
use App\Approval\Exceptions\InvalidTransitionException;
use App\Approval\Exceptions\UnauthorizedApproverException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\ReviewRemarksRequest;
use App\Http\Resources\Mobile\ProposalResource;
use App\Models\Document;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DocumentActionController extends Controller
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

    public function __construct(private readonly ReviewActivityProposal $action) {}

    public function approve(Request $request, string $proposalReference): ProposalResource
    {
        $document = $this->resolveForAction($request, $proposalReference);

        $this->run($document, fn () => $this->action->approve($document, $request->user()));

        return $this->freshResource($document, $request);
    }

    public function requestRevision(ReviewRemarksRequest $request, string $proposalReference): ProposalResource
    {
        $document = $this->resolveForAction($request, $proposalReference);
        $remarks = $request->string('remarks')->toString();
        $flaggedSections = RevisionSectionParser::parse($remarks, $document);

        $this->run($document, fn () => $this->action->returnForRevision(
            $document,
            $request->user(),
            $remarks,
            $flaggedSections === [] ? null : $flaggedSections,
        ));

        return $this->freshResource($document, $request);
    }

    public function reject(ReviewRemarksRequest $request, string $proposalReference): ProposalResource
    {
        $document = $this->resolveForAction($request, $proposalReference);

        $this->run($document, fn () => $this->action->reject(
            $document,
            $request->user(),
            $request->string('remarks')->toString(),
        ));

        return $this->freshResource($document, $request);
    }

    private function resolveForAction(Request $request, string $proposalReference): Document
    {
        $document = ProposalReference::resolve($proposalReference);

        if ($document === null) {
            abort(404, 'Activity Proposal not found.');
        }

        Gate::forUser($request->user())->authorize('review', $document);

        return $document;
    }

    /**
     * Not-your-turn, stale, and a duplicate-approval race are all the
     * same 403 to a mobile client — there is no equivalent to the web's
     * friendly "already finalized" redirect (HandlesReviewActions);
     * the app is expected to reload the document after any failed
     * action (see the plan's teammate diff list) and see the current
     * state for itself. A misconfigured chain gets the web's own
     * message, logged the same way.
     */
    private function run(Document $document, callable $callback): void
    {
        try {
            $callback();
        } catch (ProposalVenueConflictException $e) {
            throw ValidationException::withMessages(['approve' => [$e->getMessage()]]);
        } catch (InvalidTransitionException|UnauthorizedApproverException|DuplicateApprovalException) {
            abort(403, 'You are not allowed to perform this action.');
        } catch (ModelNotFoundException|\LogicException $e) {
            Log::error('Approval chain resolution failed (mobile)', [
                'document_id' => $document->id,
                'exception' => $e->getMessage(),
            ]);

            abort(500, 'This document could not be processed — its next approver could not be determined. SDAO has been notified.');
        }
    }

    private function freshResource(Document $document, Request $request): ProposalResource
    {
        $document = $document->fresh(self::DETAIL_RELATIONS);

        return new ProposalResource($document, $request->user());
    }
}
