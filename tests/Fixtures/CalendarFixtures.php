<?php

namespace Tests\Fixtures;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;

/**
 * Builders for the two ways a CalendarActivity row exists: planned on an
 * Activity Calendar, or owned by an off-calendar proposal. A row is on the
 * centralized calendar only when a proposal linked to it is Approved
 * (CalendarActivity::published()).
 */
class CalendarFixtures
{
    /**
     * A planned row on an Activity Calendar document with the given status.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function plannedActivity(DocumentStatus $calendarStatus = DocumentStatus::Approved, array $attributes = [], ?Organization $organization = null): CalendarActivity
    {
        $document = Document::factory()->create(array_filter([
            'form_type' => FormType::ActivityCalendar,
            'status' => $calendarStatus,
            'organization_id' => $organization?->id,
        ]));
        $calendar = ActivityCalendar::factory()->for($document)->create();

        return CalendarActivity::factory()->for($calendar, 'calendar')->create($attributes);
    }

    /** An on-calendar proposal, in the given status, against a planned row. */
    public static function proposalFor(CalendarActivity $planned, DocumentStatus $status): Document
    {
        $document = Document::factory()->create([
            'form_type' => FormType::ActivityProposal,
            'status' => $status,
            'organization_id' => $planned->calendar->document->organization_id,
        ]);

        ActivityProposal::factory()->create([
            'document_id' => $document->id,
            'calendar_mode' => ProposalCalendarMode::OnCalendar->value,
            'calendar_activity_id' => $planned->id,
        ]);

        return $document;
    }

    /**
     * An off-calendar proposal in the given status, with its own private row.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function offCalendarProposal(DocumentStatus $status, array $attributes = [], ?Organization $organization = null): CalendarActivity
    {
        $document = Document::factory()->create(array_filter([
            'form_type' => FormType::ActivityProposal,
            'status' => $status,
            'organization_id' => $organization?->id,
        ]));
        $calendar = ActivityCalendar::factory()->for($document)->create();
        $activity = CalendarActivity::factory()->for($calendar, 'calendar')->create($attributes);

        ActivityProposal::factory()->create([
            'document_id' => $document->id,
            'calendar_mode' => ProposalCalendarMode::OffCalendar->value,
            'calendar_activity_id' => $activity->id,
        ]);

        return $activity;
    }

    /**
     * A row published through a fully approved on-calendar proposal.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function publishedActivity(array $attributes = [], ?Organization $organization = null): CalendarActivity
    {
        $planned = self::plannedActivity(DocumentStatus::Approved, $attributes, $organization);
        self::proposalFor($planned, DocumentStatus::Approved);

        return $planned;
    }
}
