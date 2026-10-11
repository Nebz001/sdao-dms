<?php

namespace App\Http\Controllers;

use App\Organizations\StudentTrackData;
use App\Support\CurrentPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The officer's "Track Documents" page: where each of their organization's
 * documents is in its approval chain. Read only. The documents are exactly the
 * ones the officer can already see (Document::visibleToOfficer()), so an
 * officer of another organization, or anyone with no seat, gets an empty page:
 * the query is the authorization, same idiom as the document history.
 */
class TrackDocumentsController extends Controller
{
    public function index(Request $request, StudentTrackData $track): Response
    {
        $user = Auth::user();
        $membership = $user->organizationMemberships()->active()->with('organization')->first();

        return Inertia::render('track/index', [
            'organization' => $membership === null ? null : ['id' => $membership->organization->id, 'name' => $membership->organization->name],
            'period' => CurrentPeriod::get()->academicYear,
            'search' => $request->string('search')->trim()->toString(),
            'track' => $track->page(
                $user,
                $request->string('tab')->toString(),
                $request->string('search')->trim()->toString(),
                max(1, $request->integer('page', 1)),
            ),
        ]);
    }
}
