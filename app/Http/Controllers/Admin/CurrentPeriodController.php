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
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin screen for the global "current academic period" setting. Only this
 * screen changes it; every other read goes through App\Support\CurrentPeriod.
 * Setting the term to 3rd opens organization renewal season and notifies the
 * officers of every organization whose renewal is due (App\Renewals\OpenRenewalSeason).
 */
class CurrentPeriodController extends Controller
{
    public function edit(SubmitOrganizationRenewal $renewalAction, OrganizationMembershipService $membershipService): Response
    {
        $current = CurrentPeriod::get();

        return Inertia::render('admin/settings/period', [
            'current' => [
                'academic_year' => $current->academicYear,
                'term' => $current->term->value,
                'label' => $current->label(),
                'is_renewal_season' => $current->isRenewalSeason(),
            ],
            'terms' => collect(Term::cases())->map(fn (Term $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'order' => $t->order(),
            ]),
            'academicYears' => $this->nearbyAcademicYears($current->startYear()),
            'suggestedAcademicYearOnWrap' => $current->next()->academicYear,
            'renewalNoticeRecipientCount' => $this->previewRenewalRecipientCount($current, $renewalAction, $membershipService),
        ]);
    }

    public function update(UpdateCurrentPeriodRequest $request, OpenRenewalSeason $openRenewalSeason): RedirectResponse
    {
        $previous = CurrentPeriod::get();
        $new = new AcademicPeriod($request->string('academic_year')->toString(), Term::from($request->string('term')->toString()));

        CurrentPeriod::set($new);

        $notifiedCount = $openRenewalSeason->execute($previous, $new);

        $message = "Current period updated to {$new->label()}. Documents already submitted or approved are unchanged.";

        if ($notifiedCount > 0) {
            $message .= " Renewal season opened — {$notifiedCount} organization(s) notified that renewal is now due.";
        } elseif ($new->isRenewalSeason() && ! $previous->isRenewalSeason()) {
            $message .= ' Renewal season opened — no organizations are currently due.';
        }

        return redirect()->route('admin.settings.period.edit')->with('flash', ['message' => $message]);
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
     * Previews how many OFFICERS would be emailed if SDAO picked 3rd term of
     * the CURRENT academic year right now — the ordinary "advance within this
     * year" case the settings screen defaults to. Does not mutate any state;
     * evaluates SubmitOrganizationRenewal::eligibilityFor() against a
     * hypothetical period rather than the real stored one, purely so the
     * confirm dialog can name a number before the admin commits. Mirrors
     * OpenRenewalSeason's own recipient computation.
     */
    private function previewRenewalRecipientCount(
        AcademicPeriod $current,
        SubmitOrganizationRenewal $renewalAction,
        OrganizationMembershipService $membershipService,
    ): int {
        if ($current->isRenewalSeason()) {
            // Already on 3rd term — saving 3rd term again does not re-open
            // the season (OpenRenewalSeason only fires on entry into it).
            return 0;
        }

        $hypothetical = new AcademicPeriod($current->academicYear, Term::ThirdTerm);

        $dueOrganizationIds = Organization::query()
            ->get(['id'])
            ->filter(fn (Organization $org) => $renewalAction->eligibilityFor($org, $hypothetical)->status === RenewalEligibility::Eligible)
            ->pluck('id');

        if ($dueOrganizationIds->isEmpty()) {
            return 0;
        }

        return $membershipService->activeOfficersForOrganizations($dueOrganizationIds)->count();
    }
}
