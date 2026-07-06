<?php

namespace Database\Seeders;

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Enums\ProposalCalendarMode;
use App\Models\CalendarActivity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds activity proposals for manual verification:
 *
 * 1. Draft (resume demo): IT Guild — on-calendar, links to the Approved "Tech Fair"
 *    activity seeded by CalendarSeeder. Stays as a Draft so it appears in the
 *    "Continue" list on the index page.
 *
 * 2. InReview (tentative demo): SHS Student Council — off-calendar at "SHS Gymnasium",
 *    submitted via the SHS off-calendar chain. Lands at step 1 (SDAO) after submission.
 *    The off-calendar activity appears as tentative on the shared /calendar view.
 */
class ProposalSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(
        private readonly StartProposalDraft $startDraft,
        private readonly SubmitActivityProposal $submitProposal,
    ) {}

    public function run(): void
    {
        $this->seedDraftProposal();
        $this->seedInReviewOffCalendarProposal();
    }

    private function seedDraftProposal(): void
    {
        $itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
        $studentBeta = User::where('email', 'student-beta@sdao.test')->firstOrFail();

        // Find the Approved "Tech Fair" CalendarActivity seeded by CalendarSeeder.
        $techFair = CalendarActivity::query()
            ->where('name', 'Tech Fair')
            ->whereHas('calendar.document', fn ($q) => $q
                ->where('organization_id', $itGuild->id)
                ->where('status', 'approved'))
            ->first();

        if (! $techFair) {
            return;
        }

        $this->startDraft->execute(
            actor: $studentBeta,
            organization: $itGuild,
            mode: ProposalCalendarMode::OnCalendar,
            data: ['calendar_activity_id' => $techFair->id],
        );
    }

    private function seedInReviewOffCalendarProposal(): void
    {
        $shsCouncil = Organization::where('name', 'SHS Student Council')->firstOrFail();
        $studentGamma = User::where('email', 'student-gamma@sdao.test')->firstOrFail();

        $document = $this->startDraft->execute(
            actor: $studentGamma,
            organization: $shsCouncil,
            mode: ProposalCalendarMode::OffCalendar,
            data: [
                'title' => 'SHS Leadership Summit',
                'venue' => 'SHS Gymnasium',
                'activity_date' => '2026-10-20',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'term' => 'first_term',
            ],
        );

        $this->submitProposal->execute(
            actor: $studentGamma,
            document: $document,
            objectives: 'Develop leadership skills among SHS student representatives.',
            narrative: 'A full-day leadership summit for SHS officers featuring workshops and guest speakers.',
        );
    }
}
