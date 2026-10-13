<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RenewalEligibility;
use App\Enums\Term;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCurrentPeriodRequest;
use App\Models\Organization;
use App\Organizations\OrganizationMembershipService;
use App\Renewals\OpenRenewalSeason;
use App\Renewals\SubmitOrganizationRenewal;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin screen for the global "current academic period" setting. Only this
 * screen changes it; every other read goes through App\Support\CurrentPeriod.
 * Moving forward to a new term opens that term's renewal round and notifies the
 * officers of every organization whose renewal is due (App\Renewals\OpenRenewalSeason).
 */
class CurrentPeriodController extends Controller
{
    public function edit(SubmitOrganizationRenewal $renewalAction, OrganizationMembershipService $membershipService, OpenRenewalSeason $openRenewalSeason): Response
    {
        $current = CurrentPeriod::get();

        return Inertia::render('admin/settings/period', [
            'current' => [
                'academic_year' => $current->academicYear,
                'term' => $current->term->value,
                'label' => $current->label(),
            ],
            'terms' => collect(Term::cases())->map(fn (Term $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'order' => $t->order(),
            ]),
            'academicYears' => $this->nearbyAcademicYears($current->startYear()),
            'suggestedAcademicYearOnWrap' => $current->next()->academicYear,
            'renewalNoticeRecipientCounts' => $this->previewRenewalRecipientCounts($current, $renewalAction, $membershipService, $openRenewalSeason),
        ]);
    }

    public function update(UpdateCurrentPeriodRequest $request, OpenRenewalSeason $openRenewalSeason): RedirectResponse
    {
        $previous = CurrentPeriod::get();
        $new = new AcademicPeriod($request->string('academic_year')->toString(), Term::from($request->string('term')->toString()));

        CurrentPeriod::set($new);

        $notifiedCount = $openRenewalSeason->execute($previous, $new);

        $message = "The period is now {$new->label()}. Documents already submitted or approved are unchanged.";

        if ($notifiedCount > 0) {
            $message .= " Renewal opened for {$new->label()} — {$notifiedCount} organization(s) notified that renewal is now due.";
        }

        return redirect()->route('admin.settings.period.edit')->with('flash', FlashToast::make('Current period updated', $message));
    }

    /**
     * A bounded window around the current year (±2), always including it —
     * a Select, not free text, so a typo is impossible while still allowing
     * SDAO to correct a wrong year.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function nearbyAcademicYears(int $centerStartYear): array
    {
        $years = [];

        for ($startYear = $centerStartYear - 2; $startYear <= $centerStartYear + 2; $startYear++) {
            $value = "{$startYear}-".($startYear + 1);
            $years[] = ['value' => $value, 'label' => $value];
        }

        return $years;
    }

    /**
     * Previews how many OFFICERS would be emailed for each period SDAO is
     * likely to move to next: the terms of the current year and the 1st term
     * of the following year, keyed by the period string. Does not mutate any
     * state; evaluates SubmitOrganizationRenewal::eligibilityFor() against a
     * hypothetical period rather than the real stored one, purely so the
     * confirm dialog can name a number before the admin commits. Mirrors
     * OpenRenewalSeason's own recipient computation. A period that was
     * already announced, or that is not ahead of the current one, notifies
     * nobody, so it reads 0.
     *
     * @return array<string, int>
     */
    private function previewRenewalRecipientCounts(
        AcademicPeriod $current,
        SubmitOrganizationRenewal $renewalAction,
        OrganizationMembershipService $membershipService,
        OpenRenewalSeason $openRenewalSeason,
    ): array {
        $candidates = collect(Term::cases())
            ->map(fn (Term $term) => new AcademicPeriod($current->academicYear, $term))
            ->push(new AcademicPeriod($current->nextAcademicYear(), Term::FirstTerm));

        return $candidates->mapWithKeys(function (AcademicPeriod $candidate) use ($current, $renewalAction, $membershipService, $openRenewalSeason) {
            if (! $candidate->isAfter($current) || $openRenewalSeason->wasAnnounced($candidate)) {
                return [$candidate->toString() => 0];
            }

            $dueOrganizationIds = Organization::query()
                ->get(['id'])
                ->filter(fn (Organization $org) => $renewalAction->eligibilityFor($org, $candidate)->status === RenewalEligibility::Eligible)
                ->pluck('id');

            return [$candidate->toString() => $dueOrganizationIds->isEmpty()
                ? 0
                : $membershipService->activeOfficersForOrganizations($dueOrganizationIds)->count()];
        })->all();
    }
}
