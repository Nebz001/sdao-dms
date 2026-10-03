<?php

namespace App\Organizations;

use App\Approval\ReviewQueueData;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationStatus;
use App\Enums\RenewalEligibility;
use App\Enums\Term;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;

/**
 * Everything the admin organization detail page shows, read from existing
 * data only (OrganizationStatusResolver, documents and their transitions,
 * memberships, role assignments). Each public method backs one deferred
 * section of the page.
 *
 * There are no per-organization due dates or stored renewal-window dates:
 * "renewal window" months come from the term calendar in AcademicPeriod
 * (renewal season is 3rd term).
 */
class OrganizationDetailData
{
    public function __construct(
        private readonly OrganizationStatusResolver $resolver,
    ) {}

    /**
     * Header facts, status banner and the stat tiles.
     *
     * @return array<string, mixed>
     */
    public function summary(Organization $organization): array
    {
        $organization->loadMissing(['school:id,name', 'program:id,name']);
        $result = $this->resolver->for($organization);
        $requirements = collect($result->requirements->toArray());
        $registration = $this->approvedRegistration($organization);

        return [
            'status' => $result->status->value,
            'school' => $organization->school?->name,
            'program' => $organization->program?->name,
            'banner' => $this->banner($organization, $result),
            'tiles' => [
                'requirementsMet' => $requirements->where('met', true)->count(),
                'requirementsTotal' => $requirements->count(),
                'officerCount' => OrganizationMembership::query()->active()->where('organization_id', $organization->id)->count(),
                'registeredOn' => $registration ? $this->approvedAt($registration)?->toIso8601String() : null,
                'renewal' => $this->renewalTile($result),
            ],
        ];
    }

    /**
     * One row per requirement, met or not, with the document or person that
     * satisfied it when there is one.
     *
     * @return list<array<string, mixed>>
     */
    public function requirements(Organization $organization): array
    {
        $result = $this->resolver->for($organization);
        $memberships = OrganizationMembership::query()->active()->where('organization_id', $organization->id)->with('user:id,name')->get()->keyBy(fn ($m) => $m->position->value);
        $adviser = $organization->adviser()->with('user:id,name')->first();

        $renewalForNextYear = Document::query()
            ->where('organization_id', $organization->id)
            ->where('form_type', FormType::OrganizationRenewal->value)
            ->where('status', '!=', DocumentStatus::Rejected->value)
            ->whereHas('registrationDetail', fn ($q) => $q->where('covers_academic_year', CurrentPeriod::get()->nextAcademicYear()))
            ->latest('id')
            ->first();

        return collect($result->requirements->toArray())->map(function (array $item) use ($organization, $memberships, $adviser, $renewalForNextYear) {
            $document = null;
            $detail = null;
            $detailNote = null;
            $date = null;

            switch ($item['key']) {
                case 'registration_approved':
                    $document = $this->approvedRegistration($organization);
                    $date = $document ? $this->approvedAt($document) : null;
                    $detail = $document?->submitter?->name;
                    $detailNote = $document?->registrationDetail?->covers_academic_year;
                    break;
                case 'adviser_bound':
                    $detail = $adviser?->user?->name;
                    // The adviser is bound at the moment the registration is
                    // approved; fall back to the assignment's last change.
                    $registration = $adviser ? $this->approvedRegistration($organization) : null;
                    $date = $adviser ? ($registration ? $this->approvedAt($registration) : null) ?? $adviser->updated_at : null;
                    break;
                case 'president_bound':
                case 'secretary_bound':
                    $membership = $memberships->get($item['key'] === 'president_bound' ? 'president' : 'secretary');
                    $detail = $membership?->user?->name;
                    $date = $membership?->started_at ?? $membership?->created_at;
                    break;
                case 'renewal_filed':
                    $document = $renewalForNextYear;
                    $date = $document ? $this->approvedAt($document) : null;
                    $detail = $document && $date === null ? 'Filed, '.str_replace('_', ' ', $document->status->value) : null;
                    break;
            }

            return [
                'key' => $item['key'],
                'label' => $item['label'],
                'met' => $item['met'],
                'detail' => $detail,
                'detailNote' => $detailNote,
                'date' => $date?->toIso8601String(),
                'document' => $document ? $this->documentRef($document) : null,
            ];
        })->all();
    }

    /**
     * Every submitted (non-draft) document, newest first, plus the distinct
     * periods for the page's term filter.
     *
     * @return array{rows: list<array<string, mixed>>, periods: list<array{key: string, label: string}>}
     */
    public function documents(Organization $organization): array
    {
        $rows = Document::query()
            ->where('organization_id', $organization->id)
            ->where('status', '!=', DocumentStatus::Draft->value)
            ->with(['registrationDetail', 'activityCalendar', 'transitions'])
            ->orderByDesc('id')
            ->get()
            ->map(function (Document $d) {
                $period = $this->periodFor($d);

                return [
                    'id' => $d->id,
                    'title' => $d->title,
                    'type' => $d->form_type->label(),
                    'period' => ['key' => $period->toString(), 'label' => $period->label()],
                    'submitted_at' => ($d->transitions->firstWhere('action', TransitionAction::Submitted)?->created_at ?? $d->created_at)->toIso8601String(),
                    'status' => $d->status->value,
                    'href' => $this->hrefFor($d),
                ];
            })
            ->values();

        return [
            'rows' => $rows->all(),
            'periods' => $rows->pluck('period')->unique('key')->sortByDesc('key')->values()->all(),
        ];
    }

    /**
     * Current officers only; past officers stay in the database as history.
     *
     * @return list<array{id: int, position: string, name: string, id_number: string|null, since: string|null}>
     */
    public function officers(Organization $organization): array
    {
        return OrganizationMembership::query()
            ->active()
            ->where('organization_id', $organization->id)
            ->with('user:id,name,id_number')
            ->orderBy('position')
            ->get()
            ->map(fn (OrganizationMembership $m) => [
                'id' => $m->id,
                'position' => $m->position->label(),
                'name' => $m->user->name,
                'id_number' => $m->user->id_number,
                'since' => ($m->started_at ?? $m->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function banner(Organization $organization, OrganizationStatusResult $result): ?array
    {
        return match ($result->status) {
            OrganizationStatus::PendingReview => $this->pendingBanner($organization),
            OrganizationStatus::NeedsRenewal => [
                'type' => 'needs_renewal',
                'coveredThrough' => $result->coversThroughAcademicYear,
                'window' => $this->renewalWindow($result->coversThroughAcademicYear),
                'outstanding' => collect($result->requirements->toArray())->where('met', false)->pluck('label')->values()->all(),
            ],
            OrganizationStatus::Inactive => $this->inactiveBanner($organization, $result),
            OrganizationStatus::Active => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingBanner(Organization $organization): ?array
    {
        $document = Document::query()
            ->where('organization_id', $organization->id)
            ->whereIn('form_type', [FormType::OrganizationRegistration->value, FormType::OrganizationRenewal->value])
            ->inFlight()
            ->with('transitions')
            ->latest('id')
            ->first();

        if ($document === null) {
            return null;
        }

        $since = $document->transitions
            ->whereIn('action', [TransitionAction::Submitted, TransitionAction::Resubmitted])
            ->last()?->created_at ?? $document->created_at;
        $days = (int) $since->diffInDays(now(), true);

        return [
            'type' => 'pending',
            'kind' => $document->form_type === FormType::OrganizationRenewal ? 'renewal' : 'registration',
            'documentStatus' => $document->status->value,
            'waitingDays' => $days,
            'tier' => ReviewQueueData::tierFor($days),
            'href' => $this->hrefFor($document),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inactiveBanner(Organization $organization, OrganizationStatusResult $result): array
    {
        // "Since" is only knowable when the cause is lost officers (the last
        // end date); an organization that was never approved has no event to
        // date it from, so it falls back to when it was created.
        if ($result->eligibility->priorRecord === null) {
            return ['type' => 'inactive', 'reason' => 'no_approved_registration', 'since' => $organization->created_at?->toIso8601String()];
        }

        $lastEnded = OrganizationMembership::query()->where('organization_id', $organization->id)->max('ended_at');

        return ['type' => 'inactive', 'reason' => 'no_active_officers', 'since' => $lastEnded ? Date::parse($lastEnded)->toIso8601String() : null];
    }

    /**
     * @return array{value: string, note: string|null}
     */
    private function renewalTile(OrganizationStatusResult $result): array
    {
        $eligibility = $result->eligibility;

        return match ($eligibility->status) {
            RenewalEligibility::Eligible => ['value' => 'Open now', 'note' => 'Window runs through '.$this->renewalWindow($result->coversThroughAcademicYear)['label']],
            RenewalEligibility::AlreadyFiledThisYear => ['value' => 'Filed', 'note' => 'Covers '.$eligibility->currentPeriod->nextAcademicYear()],
            RenewalEligibility::NoPriorRecord => ['value' => 'Not yet', 'note' => 'No approved registration'],
            default => ['value' => $this->renewalWindow($result->coversThroughAcademicYear)['label'], 'note' => 'Next renewal window opens'],
        };
    }

    /**
     * The renewal window relevant to an organization: the one open now (label
     * is when it closes) or the next one to open (label is when it opens).
     *
     * @return array{open: bool, label: string}
     */
    private function renewalWindow(?string $coversThrough): array
    {
        $period = CurrentPeriod::get();

        if ($period->isRenewalSeason()) {
            return ['open' => true, 'label' => $period->termRange()[1]->subDay()->format('F Y')];
        }

        $year = max($coversThrough ?? $period->academicYear, $period->academicYear);

        return ['open' => false, 'label' => (new AcademicPeriod($year, Term::ThirdTerm))->termRange()[0]->format('F Y')];
    }

    private function approvedRegistration(Organization $organization): ?Document
    {
        return Document::query()
            ->where('organization_id', $organization->id)
            ->where('form_type', FormType::OrganizationRegistration->value)
            ->where('status', DocumentStatus::Approved->value)
            ->with(['transitions', 'submitter:id,name', 'registrationDetail'])
            ->orderBy('id')
            ->first();
    }

    private function approvedAt(Document $document): ?CarbonInterface
    {
        $document->loadMissing('transitions');

        return $document->transitions->firstWhere('action', TransitionAction::Completed)?->created_at;
    }

    /**
     * Registrations and renewals carry a stamped period, calendars their own;
     * proposals and reports have none, so theirs comes from the submission date.
     */
    private function periodFor(Document $document): AcademicPeriod
    {
        $detail = $document->registrationDetail;

        if ($detail?->academic_year !== null && $detail->term !== null) {
            return new AcademicPeriod($detail->academic_year, $detail->term);
        }

        $calendar = $document->activityCalendar;

        if ($calendar !== null) {
            return new AcademicPeriod($calendar->academic_year, $calendar->term);
        }

        return AcademicPeriod::forDate($document->created_at);
    }

    /**
     * @return array{id: int, title: string, type: string, status: string, href: string|null}
     */
    private function documentRef(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'type' => $document->form_type->label(),
            'status' => $document->status->value,
            'href' => $this->hrefFor($document),
        ];
    }

    /** Null when the signed-in admin may not open that document's review screen. */
    private function hrefFor(Document $document): ?string
    {
        return Gate::allows('reviewView', $document)
            ? route($document->form_type->reviewShowRouteName(), $document->id)
            : null;
    }
}
