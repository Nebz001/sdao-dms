<?php

namespace Database\Seeders;

use App\Approval\ApprovalEngine;
use App\Calendar\SubmitActivityCalendar;
use App\Enums\Term;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds one Approved and one InReview calendar activity for manual verification:
 *
 * Approved (hard-block target):
 *   IT Guild — "Tech Fair" at Auditorium, 2026-09-10, 13:00–15:00
 *
 * InReview (tentative-warning target for a DIFFERENT venue, same date):
 *   Computing Society — "CS Week Kickoff" at Function Hall, 2026-09-10, 10:00–12:00
 *   (different venue — no conflict; demonstrates the queue without a hard-block)
 *
 * To manually test a tentative warning, submit a new calendar for a different org
 * that overlaps IT Guild's slot at a time when IT Guild is InReview (before approval).
 * The seeder approves IT Guild to provide the hard-block baseline for testing.
 */
class CalendarSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(
        private readonly SubmitActivityCalendar $submitAction,
        private readonly ApprovalEngine $engine,
    ) {}

    public function run(): void
    {
        $itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
        $studentBeta = User::where('email', 'student-beta@sdao.test')->firstOrFail();
        $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
        $sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();

        // 1. Submit IT Guild calendar and approve it — this becomes the confirmed hard-block baseline
        $result = $this->submitAction->execute(
            actor: $studentBeta,
            organization: $itGuild,
            term: Term::FirstTerm,
            activities: [[
                'name' => 'Tech Fair',
                'venue' => 'Auditorium',
                'activity_date' => '2026-09-10',
                'start_time' => '13:00',
                'end_time' => '15:00',
                'description' => 'Annual IT Guild technology showcase.',
            ]],
        );

        $approvedDoc = $result['document'];
        $this->engine->approve($approvedDoc, $sdaoA);
        $approvedDoc->refresh();
        $this->engine->approve($approvedDoc, $sdaoB);

        // 2. Submit Computing Society calendar at a DIFFERENT venue (no conflict)
        //    This is in the InReview queue and exercises the tentative code path
        //    when another submission later overlaps it.
        $computingSoc = Organization::where('name', 'Computing Society')->firstOrFail();
        $studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();

        $this->submitAction->execute(
            actor: $studentAlpha,
            organization: $computingSoc,
            term: Term::FirstTerm,
            activities: [[
                'name' => 'CS Week Kickoff',
                'venue' => 'Function Hall',
                'activity_date' => '2026-09-10',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'description' => 'Start of CS Week celebrations.',
            ]],
        );
    }
}
