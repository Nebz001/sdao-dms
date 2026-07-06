<?php

namespace App\ActivityProposals;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Term;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use App\Support\AcademicYear;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartProposalDraft
{
    public function __construct(
        private readonly OrganizationMembershipService $membershipService,
    ) {}

    /**
     * Persist a step-1 draft for an activity proposal.
     *
     * For on-calendar: $data must contain 'calendar_activity_id' (Approved, org-owned).
     * For off-calendar: $data must contain 'title', 'venue', 'activity_date',
     *   'start_time', 'end_time', 'term'.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        Organization $organization,
        ProposalCalendarMode $mode,
        array $data,
    ): Document {
        $membership = $this->membershipService->activeMembershipFor($actor, $organization);

        if ($membership === null) {
            throw new AuthorizationException('You must be an active officer to submit for this organization.');
        }

        return DB::transaction(function () use ($actor, $organization, $mode, $data) {
            if ($mode === ProposalCalendarMode::OnCalendar) {
                return $this->startOnCalendar($actor, $organization, (int) $data['calendar_activity_id']);
            }

            return $this->startOffCalendar($actor, $organization, $data);
        });
    }

    private function startOnCalendar(User $actor, Organization $organization, int $calendarActivityId): Document
    {
        $calendarActivity = CalendarActivity::query()
            ->whereHas('calendar.document', fn ($q) => $q
                ->where('organization_id', $organization->id)
                ->where('status', DocumentStatus::Approved->value))
            ->where('id', $calendarActivityId)
            ->firstOrFail();

        $document = Document::create([
            'form_type' => FormType::ActivityProposal,
            'variant' => null,
            'title' => "Activity Proposal — {$calendarActivity->name} ({$organization->name})",
            'status' => DocumentStatus::Draft,
            'current_step_position' => null,
            'organization_id' => $organization->id,
            'workflow_template_id' => null,
            'submitted_by' => $actor->id,
        ]);

        ActivityProposal::create([
            'document_id' => $document->id,
            'calendar_mode' => ProposalCalendarMode::OnCalendar->value,
            'calendar_activity_id' => $calendarActivity->id,
            'title' => $calendarActivity->name,
            'form_step' => 2,
        ]);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function startOffCalendar(User $actor, Organization $organization, array $data): Document
    {
        $term = Term::from($data['term']);
        $academicYear = AcademicYear::current();

        // Create the document first so ActivityCalendar.document_id can point to it.
        $document = Document::create([
            'form_type' => FormType::ActivityProposal,
            'variant' => null,
            'title' => "Activity Proposal — {$data['title']} ({$organization->name})",
            'status' => DocumentStatus::Draft,
            'current_step_position' => null,
            'organization_id' => $organization->id,
            'workflow_template_id' => null,
            'submitted_by' => $actor->id,
        ]);

        // Proposal-owned calendar container — VenueConflictChecker traverses this
        // to reach document.status, giving us tentative/confirmed for free.
        $calendar = ActivityCalendar::create([
            'document_id' => $document->id,
            'academic_year' => $academicYear,
            'term' => $term->value,
        ]);

        $calendarActivity = CalendarActivity::create([
            'activity_calendar_id' => $calendar->id,
            'name' => $data['title'],
            'venue' => $data['venue'],
            'activity_date' => $data['activity_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]);

        ActivityProposal::create([
            'document_id' => $document->id,
            'calendar_mode' => ProposalCalendarMode::OffCalendar->value,
            'calendar_activity_id' => $calendarActivity->id,
            'title' => $data['title'],
            'form_step' => 2,
        ]);

        return $document;
    }
}
