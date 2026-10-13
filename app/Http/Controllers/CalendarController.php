<?php

namespace App\Http\Controllers;

use App\Calendar\ProposalAccess;
use App\Enums\DocumentStatus;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Shared venue calendar — only activities whose Activity Proposal has
     * reached final approval (CalendarActivity::published()), on-calendar and
     * off-calendar alike; the date, time and venue are the linked
     * CalendarActivity's. Everyone sees every activity; `can_view_proposal`
     * says whether this user may open the approved proposal (see
     * ProposalAccess), so the page only offers a link that works.
     */
    public function index(ProposalAccess $access): Response
    {
        $activities = CalendarActivity::query()
            ->published()
            ->with(['calendar.document.organization', 'proposals.document'])
            ->orderBy('activity_date')
            ->orderBy('start_time')
            ->get();

        $approvedProposalDocument = fn (CalendarActivity $a) => $a->proposals
            ->first(fn (ActivityProposal $p) => $p->document->status === DocumentStatus::Approved)
            ->document;

        $canView = $access->forUser(Auth::user(), $activities->map($approvedProposalDocument));

        return Inertia::render('calendar/index', [
            'activities' => $activities->map(fn (CalendarActivity $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'venue' => $a->venue,
                'activity_date' => $a->activity_date->toDateString(),
                'start_time' => $a->start_time,
                'end_time' => $a->end_time,
                'status' => DocumentStatus::Approved->value,
                'organization' => $a->calendar->document->organization->name,
                'document_id' => $approvedProposalDocument($a)->id,
                'can_view_proposal' => $canView($approvedProposalDocument($a)),
            ]),
        ]);
    }
}
