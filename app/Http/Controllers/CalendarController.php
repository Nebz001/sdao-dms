<?php

namespace App\Http\Controllers;

use App\Calendar\ProposalAccess;
use App\Enums\DocumentStatus;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Shared venue calendar — confirmed activities (CalendarActivity::published(),
     * proposal fully approved) plus tentative ones (CalendarActivity::tentative(),
     * proposal still In Review), on-calendar and off-calendar alike. A row that
     * is both shows once, as published. Everyone sees every activity;
     * `can_view_proposal` says whether this user may open the proposal (see
     * ProposalAccess and DocumentPolicy), so the page only offers a link that works.
     */
    public function index(ProposalAccess $access): Response
    {
        $with = ['calendar.document.organization', 'proposals.document'];

        $published = CalendarActivity::query()->published()->with($with)->get();
        $tentative = CalendarActivity::query()->tentative()->with($with)->get()
            ->reject(fn (CalendarActivity $a) => $published->contains('id', $a->id));

        $entries = $published
            ->map(fn (CalendarActivity $a) => [$a, $this->proposalDocument($a, DocumentStatus::Approved)])
            ->concat($tentative->map(fn (CalendarActivity $a) => [$a, $this->proposalDocument($a, DocumentStatus::InReview)]))
            ->sortBy(fn (array $e) => $e[0]->activity_date->toDateString().' '.$e[0]->start_time)
            ->values();

        $canView = $access->forUser(Auth::user(), $entries->map(fn (array $e) => $e[1]));

        return Inertia::render('calendar/index', [
            'activities' => $entries->map(fn (array $e) => [
                'id' => $e[0]->id,
                'name' => $e[0]->name,
                'venue' => $e[0]->venue,
                'activity_date' => $e[0]->activity_date->toDateString(),
                'start_time' => $e[0]->start_time,
                'end_time' => $e[0]->end_time,
                'status' => $e[1]->status->value,
                'organization' => $e[0]->calendar->document->organization->name,
                'document_id' => $e[1]->id,
                'can_view_proposal' => $canView($e[1]) && $this->policyAllowsView($e[1]),
            ]),
        ]);
    }

    private function proposalDocument(CalendarActivity $activity, DocumentStatus $status): Document
    {
        return $activity->proposals
            ->first(fn (ActivityProposal $p) => $p->document->status === $status)
            ->document;
    }

    /**
     * ProposalAccess decides who is offered the button; DocumentPolicy stays
     * the authority, so the link never leads to a screen that would 403.
     */
    private function policyAllowsView(Document $document): bool
    {
        return Gate::allows('view', $document) || Gate::allows('reviewView', $document);
    }
}
