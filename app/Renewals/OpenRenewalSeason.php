<?php

namespace App\Renewals;

use App\Enums\RenewalEligibility;
use App\Enums\Term;
use App\Models\Organization;
use App\Models\Setting;
use App\Notifications\RenewalWindowOpenedNotification;
use App\Organizations\OrganizationMembershipService;
use App\Support\AcademicPeriod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Fires when SDAO advances the current term to 3rd — the trigger for
 * organization renewal season (decision: renewal is gated to 3rd term, not
 * re-opened on every term change). Notifies active officers of every
 * organization whose renewal is genuinely due, once per academic year: an
 * admin correcting 3rd -> 2nd -> 3rd within the same year must not
 * re-broadcast (guarded by the `renewal_notice_sent_for` setting, which this
 * class exclusively owns — same one-key-per-owner convention as
 * App\Support\CurrentPeriod).
 *
 * Recipient computation loops eligibilityFor() per organization — a bounded
 * N+1 at NU Lipa's scale (dozens of organizations), run once per admin action
 * rather than per request. This intentionally reuses
 * SubmitOrganizationRenewal::eligibilityFor() as the single source of truth
 * rather than re-deriving "is this org due" here — when
 * App\Organizations\OrganizationStatusResolver lands, its bounded-query
 * organizationIdsWithRenewalDue() should replace this loop.
 */
class OpenRenewalSeason
{
    private const string SENT_FOR_KEY = 'renewal_notice_sent_for';

    public function __construct(
        private readonly SubmitOrganizationRenewal $renewalAction,
        private readonly OrganizationMembershipService $membershipService,
    ) {}

    /**
     * @return int number of organizations notified
     */
    public function execute(AcademicPeriod $previous, AcademicPeriod $new): int
    {
        if (! $this->shouldOpen($previous, $new)) {
            return 0;
        }

        $dueOrganizationIds = Organization::query()
            ->get(['id', 'name'])
            ->filter(fn (Organization $org) => $this->renewalAction->eligibilityFor($org)->status === RenewalEligibility::Eligible)
            ->pluck('id');

        if ($dueOrganizationIds->isEmpty()) {
            $this->markSent($new->academicYear);

            return 0;
        }

        $recipients = $this->membershipService->activeOfficersForOrganizations($dueOrganizationIds);

        if ($recipients->isNotEmpty()) {
            try {
                Notification::send($recipients, new RenewalWindowOpenedNotification($new));
            } catch (Throwable $e) {
                Log::error('Renewal window opened notification failed to dispatch', [
                    'academic_year' => $new->academicYear,
                    'organization_count' => $dueOrganizationIds->count(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->markSent($new->academicYear);

        return $dueOrganizationIds->count();
    }

    /**
     * Fires only on entry into 3rd term (not a re-save of an already-3rd
     * term, and not a move to 1st/2nd), and only once per academic year.
     */
    private function shouldOpen(AcademicPeriod $previous, AcademicPeriod $new): bool
    {
        if ($previous->term === Term::ThirdTerm || $new->term !== Term::ThirdTerm) {
            return false;
        }

        return Setting::query()->where('key', self::SENT_FOR_KEY)->value('value') !== $new->academicYear;
    }

    private function markSent(string $academicYear): void
    {
        Setting::query()->updateOrCreate(['key' => self::SENT_FOR_KEY], ['value' => $academicYear]);
    }
}
