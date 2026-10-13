<?php

namespace App\Renewals;

use App\Models\Setting;
use App\Notifications\RenewalWindowOpenedNotification;
use App\Organizations\OrganizationMembershipService;
use App\Organizations\OrganizationStatusResolver;
use App\Support\AcademicPeriod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Fires when SDAO moves the current period forward to a new term — the
 * trigger for that term's renewal round (every term, 1st/2nd/3rd, is a
 * renewal term). Notifies active officers of every organization whose renewal
 * is genuinely due, once per period and only on a forward move: an admin
 * correcting 2nd -> 1st -> 2nd must not re-broadcast (guarded by the
 * `renewal_notice_sent_for` setting, which holds the list of announced
 * periods as JSON and which
 * this class exclusively owns — same one-key-per-owner convention as
 * AppSupportCurrentPeriod).
 *
 * Recipient computation delegates to
 * OrganizationStatusResolver::organizationIdsWithRenewalDue(), which itself
 * loops SubmitOrganizationRenewal::eligibilityFor() per organization — a
 * bounded N+1 at NU Lipa's scale (dozens of organizations), run once per
 * admin action rather than per request. Do not re-derive "is this org due"
 * here; the resolver is the single source of truth.
 */
class OpenRenewalSeason
{
    private const string SENT_FOR_KEY = 'renewal_notice_sent_for';

    public function __construct(
        private readonly OrganizationStatusResolver $statusResolver,
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

        $dueOrganizationIds = $this->statusResolver->organizationIdsWithRenewalDue($new);

        if ($dueOrganizationIds->isEmpty()) {
            $this->markSent($new);

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

        $this->markSent($new);

        return $dueOrganizationIds->count();
    }

    /**
     * Fires only when the period moves FORWARD (a backwards correction or a
     * re-save of the same term never announces anything), and only once per
     * period.
     */
    private function shouldOpen(AcademicPeriod $previous, AcademicPeriod $new): bool
    {
        return $new->isAfter($previous) && ! $this->wasAnnounced($new);
    }

    /**
     * Whether officers were already told about this period's renewal round.
     */
    public function wasAnnounced(AcademicPeriod $period): bool
    {
        return in_array($period->toString(), $this->announcedPeriods(), true);
    }

    /**
     * The setting holds a JSON list of period strings. A value from before
     * renewal went per-term (a bare academic year) is not valid JSON, so it
     * reads as "nothing announced yet".
     *
     * @return list<string>
     */
    private function announcedPeriods(): array
    {
        $decoded = json_decode((string) Setting::query()->where('key', self::SENT_FOR_KEY)->value('value'), true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function markSent(AcademicPeriod $period): void
    {
        Setting::query()->updateOrCreate(
            ['key' => self::SENT_FOR_KEY],
            ['value' => json_encode([...$this->announcedPeriods(), $period->toString()])],
        );
    }
}
