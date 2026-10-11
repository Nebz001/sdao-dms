<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\TransitionAction;
use App\Models\ActivityProposal;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Organizations\StudentTrackData;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * The officer's Track Documents page and the home banner's counts. Both read
 * the same documents (Document::visibleToOfficer()) and the same
 * document_transitions; nothing is written.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->withoutVite();
    $this->computing = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->otherOfficer = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->otherAdviser = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    $this->sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->clock = now()->subDays(10);
});

/**
 * @param  list<array{0: TransitionAction, 1: int|null, 2?: User|null, 3?: string|null}>  $transitions  action, step, actor, comment, one day apart
 */
function trackDocument(
    FormType $type,
    Organization $org,
    DocumentStatus $status,
    ?int $currentStep,
    array $transitions = [],
    ?ProposalVariant $variant = null,
    ?string $activity = null,
): Document {
    $template = $status === DocumentStatus::Draft ? null : WorkflowTemplate::query()
        ->where('form_type', $type->value)
        ->where('variant', $variant?->value)
        ->whereNull('retired_at')
        ->firstOrFail();

    $document = Document::create([
        'form_type' => $type,
        'variant' => $variant,
        'title' => "{$type->label()} — Stored Title ({$org->name})",
        'status' => $status,
        'current_step_position' => $currentStep,
        'organization_id' => $org->id,
        'workflow_template_id' => $template?->id,
        'submitted_by' => null,
    ]);

    if ($activity !== null) {
        ActivityProposal::factory()->create(['document_id' => $document->id, 'title' => $activity, 'form_step' => 2]);
    }

    foreach ($transitions as $i => $row) {
        DocumentTransition::create([
            'document_id' => $document->id,
            'actor_id' => ($row[2] ?? null)?->id,
            'action' => $row[0],
            'from_status' => DocumentStatus::InReview,
            'to_status' => DocumentStatus::InReview,
            'step_position' => $row[1],
            'comment' => $row[3] ?? null,
            'created_at' => now()->subDays(10 - $i),
        ]);
    }

    return $document;
}

function trackPage(User $user, array $query = [])
{
    return test()->actingAs($user)->get(route('track.index', $query));
}

function proposalInReview(Organization $org, int $step, ?ProposalVariant $variant = null, string $name = 'Hack Night'): Document
{
    $submit = [TransitionAction::Submitted, 1, test()->president];
    $moves = [];
    for ($i = 1; $i < $step; $i++) {
        $moves[] = [TransitionAction::Approved, $i, test()->adviser];
        $moves[] = [TransitionAction::Advanced, $i + 1, test()->adviser];
    }

    return trackDocument(FormType::ActivityProposal, $org, DocumentStatus::InReview, $step, [$submit, ...$moves], $variant ?? ProposalVariant::RegularOnCalendar, $name);
}

describe('the chain is the document\'s own', function () {
    it('draws every step of the on-calendar and off-calendar chains as configured, between Submitted and Approved', function (ProposalVariant $variant) {
        proposalInReview($this->computing, 2, $variant);
        $template = WorkflowTemplate::where('form_type', FormType::ActivityProposal->value)->where('variant', $variant->value)->whereNull('retired_at')->firstOrFail();
        $expected = $template->steps->map(fn ($step) => StudentTrackData::stepLabel($step->role))->all();

        trackPage($this->president)->assertOk()->assertInertia(fn ($page) => $page
            ->component('track/index')
            ->where('track.rows.0.steps', fn ($steps) => collect($steps)->pluck('label')->all() === ['Submitted', ...$expected, 'Approved']));
    })->with([
        'regular on calendar' => [ProposalVariant::RegularOnCalendar],
        'regular off calendar' => [ProposalVariant::RegularOffCalendar],
    ]);

    it('uses the principal and no program chair or dean for a Senior High School proposal', function () {
        proposalInReview($this->computing, 2, ProposalVariant::ShsOnCalendar);

        trackPage($this->president)->assertOk()->assertInertia(fn ($page) => $page
            ->where('track.rows.0.steps', function ($steps) {
                $labels = collect($steps)->pluck('label');

                return $labels->contains('Principal')
                    && ! $labels->contains('Program Chair')
                    && ! $labels->contains('College Dean');
            }));
    });

    it('is Adviser then SDAO for an after-activity report', function () {
        trackDocument(FormType::AfterActivityReport, $this->computing, DocumentStatus::InReview, 1, [[TransitionAction::Submitted, 1, $this->president]]);

        trackPage($this->president)->assertOk()->assertInertia(fn ($page) => $page
            ->where('track.rows.0.steps', fn ($steps) => collect($steps)->pluck('label')->all() === ['Submitted', 'Adviser', 'SDAO', 'Approved']));
    });
});

describe('step states come from document_transitions', function () {
    it('marks done steps with their approval date, the current step with how long it has been there, and the rest next or waiting', function () {
        $document = proposalInReview($this->computing, 3);
        // Make updated_at lie: only the transitions may be believed.
        Document::whereKey($document->id)->update(['updated_at' => now()->addYear()]);

        trackPage($this->president)->assertOk()->assertInertia(fn ($page) => $page
            ->where('track.rows.0.steps', function ($steps) {
                $steps = collect($steps)->values();

                return $steps[0]['state'] === 'done'
                    && $steps[1]['state'] === 'done' && $steps[1]['date'] !== null
                    && $steps[2]['state'] === 'done'
                    && $steps[3]['state'] === 'current' && $steps[3]['hereDays'] >= 5
                    && $steps[4]['state'] === 'upcoming' && $steps[4]['isNext'] === true
                    && $steps[5]['state'] === 'upcoming' && $steps[5]['isNext'] === false;
            }));
    });

    it('renders a returned step in amber with the approver\'s note, and later steps waiting', function () {
        $document = trackDocument(FormType::ActivityProposal, $this->computing, DocumentStatus::Returned, 2, [
            [TransitionAction::Submitted, 1, $this->president],
            [TransitionAction::Approved, 1, $this->adviser],
            [TransitionAction::Advanced, 2, $this->adviser],
            [TransitionAction::Returned, 2, $this->sdao, 'Please attach the signed sheet.'],
        ], ProposalVariant::RegularOnCalendar, 'Hack Night');

        trackPage($this->president)->assertOk()->assertInertia(fn ($page) => $page
            ->where('track.rows.0.status', 'returned')
            ->where('track.rows.0.action.label', 'Fix and resend')
            ->where('track.rows.0.action.href', route('activity-proposals.edit', $document))
            ->where('track.rows.0.note.comment', 'Please attach the signed sheet.')
            ->where('track.rows.0.steps', function ($steps) {
                $steps = collect($steps)->values();

                return $steps[1]['state'] === 'done' && $steps[2]['state'] === 'returned' && $steps[3]['state'] === 'upcoming';
            }));
    });

    it('renders a rejected step in red and grays out everything after it', function () {
        trackDocument(FormType::ActivityProposal, $this->computing, DocumentStatus::Rejected, null, [
            [TransitionAction::Submitted, 1, $this->president],
            [TransitionAction::Approved, 1, $this->adviser],
            [TransitionAction::Advanced, 2, $this->adviser],
            [TransitionAction::Rejected, 2, $this->sdao, 'Not allowed.'],
        ], ProposalVariant::RegularOnCalendar, 'Hack Night');

        trackPage($this->president, ['tab' => 'stopped'])->assertOk()->assertInertia(fn ($page) => $page
            ->where('track.rows.0.steps', function ($steps) {
                $steps = collect($steps)->values();

                return $steps[2]['state'] === 'rejected'
                    && $steps[3]['state'] === 'upcoming' && $steps[3]['muted'] === true
                    && $steps->last()['muted'] === true;
            }));
    });

    it('shows a fully approved document with every step done', function () {
        trackDocument(FormType::AfterActivityReport, $this->computing, DocumentStatus::Approved, null, [
            [TransitionAction::Submitted, 1, $this->president],
            [TransitionAction::Approved, 1, $this->adviser],
            [TransitionAction::Advanced, 2, $this->adviser],
            [TransitionAction::Approved, 2, $this->sdao],
            [TransitionAction::Completed, 2, $this->sdao],
        ]);

        trackPage($this->president)->assertOk()->assertInertia(fn ($page) => $page
            ->where('track.rows.0.steps', fn ($steps) => collect($steps)->every(fn ($s) => $s['state'] === 'done')));
    });
});

describe('the step popover', function () {
    it('names the person who actually acted on a past step, not today\'s role holder, and shows an email only for the adviser', function () {
        // Adviser Two approved a Computing Society proposal; the seat's holder today is Adviser One.
        trackDocument(FormType::ActivityProposal, $this->computing, DocumentStatus::InReview, 2, [
            [TransitionAction::Submitted, 1, $this->president],
            [TransitionAction::Approved, 1, $this->otherAdviser],
            [TransitionAction::Advanced, 2, $this->otherAdviser],
        ], ProposalVariant::RegularOnCalendar, 'Hack Night');

        trackPage($this->president)->assertOk()->assertInertia(fn ($page) => $page
            ->where('track.rows.0.steps', function ($steps) {
                $adviser = collect($steps)->firstWhere('label', 'Adviser');
                $current = collect($steps)->firstWhere('state', 'current');

                return $adviser['popover']['status'] === 'Approved'
                    && $adviser['popover']['people'][0]['name'] === test()->otherAdviser->name
                    && $adviser['popover']['people'][0]['email'] === test()->otherAdviser->email
                    && $current['popover']['status'] === 'Reviewing now'
                    && collect($current['popover']['people'])->every(fn ($p) => $p['email'] === null);
            }));
    });

    it('says how a step is waiting for someone when its seat is empty, never failing', function () {
        proposalInReview($this->computing, 2);

        trackPage($this->president)->assertOk();
    });
});

describe('tabs, search, scope and isolation', function () {
    it('counts and filters by tab, reads the tab from the URL, and searches by the clean title', function () {
        proposalInReview($this->computing, 2, null, 'Bridge Design Challenge');
        trackDocument(FormType::ActivityProposal, $this->computing, DocumentStatus::Returned, 1, [
            [TransitionAction::Submitted, 1, $this->president],
            [TransitionAction::Returned, 1, $this->adviser, 'Fix it.'],
        ], ProposalVariant::RegularOnCalendar, 'Site Visit Seminar');
        trackDocument(FormType::AfterActivityReport, $this->computing, DocumentStatus::Approved, null, [
            [TransitionAction::Submitted, 1, $this->president],
            [TransitionAction::Completed, 2, $this->sdao],
        ]);

        trackPage($this->president)->assertInertia(fn ($page) => $page
            ->where('track.counts', ['all' => 3, 'action' => 1, 'moving' => 1, 'approved' => 1, 'stopped' => 0])
            ->where('track.tab', 'all')
            ->has('track.rows', 3));

        trackPage($this->president, ['tab' => 'action'])->assertInertia(fn ($page) => $page
            ->where('track.tab', 'action')
            ->has('track.rows', 1)
            ->where('track.rows.0.title', 'Site Visit Seminar'));

        trackPage($this->president, ['tab' => 'moving'])->assertInertia(fn ($page) => $page
            ->has('track.rows', 1)
            ->where('track.rows.0.title', 'Bridge Design Challenge')
            ->where('track.rows.0.showFormType', true));

        // The title helper's output, not the stored "… — Stored Title (…)".
        trackPage($this->president, ['search' => 'bridge'])->assertInertia(fn ($page) => $page
            ->has('track.rows', 1)
            ->where('track.counts.all', 1));
        trackPage($this->president, ['search' => 'Stored Title'])->assertInertia(fn ($page) => $page->has('track.rows', 0));

        trackPage($this->president, ['tab' => 'nonsense'])->assertInertia(fn ($page) => $page->where('track.tab', 'all'));
    });

    it('lists an unfinished draft under Needs your action, without routing steps', function () {
        trackDocument(FormType::ActivityProposal, $this->computing, DocumentStatus::Draft, null, [], null, 'Half Done');

        trackPage($this->president, ['tab' => 'action'])->assertInertia(fn ($page) => $page
            ->has('track.rows', 1)
            ->where('track.rows.0.steps', null)
            ->where('track.rows.0.action.label', 'Continue draft'));
    });

    it('keeps old approved documents out but keeps anything still open', function () {
        $old = trackDocument(FormType::AfterActivityReport, $this->computing, DocumentStatus::Approved, null, [[TransitionAction::Submitted, 1, $this->president]]);
        $oldOpen = trackDocument(FormType::AfterActivityReport, $this->computing, DocumentStatus::InReview, 1, [[TransitionAction::Submitted, 1, $this->president]]);
        Document::whereIn('id', [$old->id, $oldOpen->id])->update(['created_at' => now()->subYears(3)]);

        trackPage($this->president)->assertInertia(fn ($page) => $page
            ->has('track.rows', 1)
            ->where('track.rows.0.id', $oldOpen->id));
    });

    it('never shows another organization\'s documents, and gives a non-officer an empty page', function () {
        proposalInReview($this->computing, 2);

        trackPage($this->otherOfficer)->assertOk()->assertInertia(fn ($page) => $page
            ->has('track.rows', 0)
            ->where('track.counts.all', 0));

        $stranger = User::factory()->create(['account_status' => 'verified']);
        trackPage($stranger)->assertOk()->assertInertia(fn ($page) => $page
            ->where('organization', null)
            ->has('track.rows', 0));
    });

    it('paginates ten to a page', function () {
        foreach (range(1, 12) as $i) {
            trackDocument(FormType::AfterActivityReport, $this->computing, DocumentStatus::InReview, 1, [[TransitionAction::Submitted, 1, $this->president]]);
        }

        trackPage($this->president)->assertInertia(fn ($page) => $page
            ->has('track.rows', 10)
            ->where('track.meta.total', 12)
            ->where('track.meta.last_page', 2));
        trackPage($this->president, ['page' => 2])->assertInertia(fn ($page) => $page->has('track.rows', 2));
    });

    it('does not run more queries per document as documents are added', function () {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            trackPage($this->president)->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        foreach (range(1, 2) as $i) {
            proposalInReview($this->computing, 2, null, "Doc {$i}");
        }
        $few = $count();

        foreach (range(3, 9) as $i) {
            proposalInReview($this->computing, 2, null, "Doc {$i}");
        }
        $many = $count();

        expect($many - $few)->toBeLessThanOrEqual(2);
    });
});

describe('home banner counts', function () {
    it('counts what needs action, what is moving and where, what is approved this term, from the same documents', function () {
        $track = app(StudentTrackData::class);

        expect($track->summary($this->president)['total'])->toBe(0);

        proposalInReview($this->computing, 1, null, 'A');
        proposalInReview($this->computing, 2, null, 'B');
        trackDocument(FormType::ActivityProposal, $this->computing, DocumentStatus::Returned, 1, [
            [TransitionAction::Submitted, 1, $this->president],
            [TransitionAction::Returned, 1, $this->adviser, 'Fix.'],
        ], ProposalVariant::RegularOnCalendar, 'C');
        trackDocument(FormType::AfterActivityReport, $this->computing, DocumentStatus::Approved, null, [
            [TransitionAction::Submitted, 1, $this->president],
            [TransitionAction::Completed, 2, $this->sdao],
        ]);
        // Another organization's document never counts.
        proposalInReview($this->itGuild, 1, null, 'Other');

        $summary = $track->summary($this->president);

        expect($summary)->toMatchArray(['returned' => 1, 'drafts' => 0, 'moving' => 2, 'approved' => 1, 'total' => 4]);
        expect(collect($summary['movingByStep'])->sum('count'))->toBe(2);
        expect(collect($summary['movingByStep'])->pluck('label')->all())->toContain('Adviser');
    });

    it('is sent to the home page as a deferred prop for an officer', function () {
        $this->actingAs($this->president)->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->missing('studentTrackSummary')
            ->loadDeferredProps('student', fn ($reload) => $reload
                ->has('studentTrackSummary.total')
                ->has('studentTrackSummary.movingByStep')));
    });
});

describe('the popover\'s decision and waiting rows', function () {
    it('carries the decision date per state, the waiting days for the current step, and no person or date for a future step', function () {
        trackDocument(FormType::ActivityProposal, $this->computing, DocumentStatus::Returned, 2, [
            [TransitionAction::Submitted, 1, $this->president],
            [TransitionAction::Approved, 1, $this->adviser],
            [TransitionAction::Advanced, 2, $this->adviser],
            [TransitionAction::Returned, 2, $this->sdao, 'Fix it.'],
        ], ProposalVariant::RegularOnCalendar, 'Hack Night');
        proposalInReview($this->itGuild, 2); // another organization's, never listed

        trackPage($this->president)->assertOk()->assertInertia(fn ($page) => $page
            ->where('track.rows.0.steps', function ($steps) {
                $steps = collect($steps)->values();
                $done = $steps[1]['popover'];
                $returned = $steps[2]['popover'];
                $future = $steps[3]['popover'];

                return $done['decision']['label'] === 'Approved' && $done['decision']['at'] !== null && $done['receivedAt'] !== null
                    // The person who returned it is shown, with when.
                    && $returned['decision']['label'] === 'Returned' && $returned['people'][0]['name'] === test()->sdao->name
                    && $returned['waitingDays'] === null
                    // Ahead: the role and Waiting only.
                    && $future['status'] === 'Waiting' && $future['people'] === [] && $future['receivedAt'] === null
                    && $future['decision'] === null && $future['roleText'] !== '';
            }));

        $document = proposalInReview($this->computing, 2, null, 'Open One');
        trackPage($this->president, ['search' => 'Open One'])->assertInertia(fn ($page) => $page
            ->where('track.rows.0.steps', function ($steps) {
                $current = collect($steps)->firstWhere('state', 'current')['popover'];

                return $current['status'] === 'Reviewing now' && $current['waitingDays'] >= 0 && $current['receivedAt'] !== null && $current['decision'] === null;
            }));
        expect($document)->not->toBeNull();
    });
});
