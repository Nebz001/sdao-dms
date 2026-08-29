<?php

namespace App\Approval;

use App\Enums\FormType;
use App\Models\OrganizationRegistrationDetail;

/**
 * Static registry of the FIELDS that belong to each flaggable section
 * (field-level revision diffs). The exact counterpart of App\Approval\SectionFlags:
 * SectionFlags answers "what can be flagged", SectionFields answers "what
 * does flagging that actually cover".
 *
 * Data only — no diffing, no formatting, no DB queries. See FieldChangeSet
 * for the pairing logic and FieldValueFormatter for rendering.
 *
 * Sections deliberately ABSENT here (flagging them is meaningful but
 * produces no FIELD diff, exactly as intended):
 *   - 'general'                      — no fields by definition
 *   - every attachment slot key      — no scalar fields to diff; a flagged
 *                                      attachment slot instead gets a
 *                                      replaced/added/unchanged STATUS
 *                                      marker (no filenames) via
 *                                      FieldChangeSet::build()'s own
 *                                      AttachmentSlots lookup, for the three
 *                                      form types that flag attachments this
 *                                      way (Registration, Renewal, After-
 *                                      Activity Report). ActivityProposal's
 *                                      one optional attachment is the
 *                                      exception — see 'resource_person'
 *                                      below.
 *   - 'resource_person' (Proposal)   — attachment-only, but uploaded via the
 *                                      standalone Mode B endpoint
 *                                      (AttachmentController), independent of
 *                                      any resubmit request — there is no
 *                                      "this resubmit's files" payload to
 *                                      compare against, so the marker above
 *                                      does not apply here. A real gap, not
 *                                      an oversight.
 *   - 'event_details' (Report)       — a read-only echo of the linked
 *                                      CalendarActivity; zero editable fields
 *   - 'adviser_selection' on RENEWAL — the key exists in SectionFlags (shared
 *                                      match arm with Registration) but the
 *                                      renewal form has no adviser field, so
 *                                      there is nothing to diff — same
 *                                      "no fields" shape as general.
 *
 * Activity Calendar is the same exception it is in SectionFlags: its
 * sections are positional "activity_{i}" rows with no stable identity across
 * a return/resubmit cycle, so it has no per-section map here — one fixed
 * per-row template (calendarFields()) applies to whichever indices were
 * flagged.
 *
 * Labels are duplicated from each form's own request-validation "friendly
 * name" maps on purpose: making those protected methods reusable would mean
 * touching five unrelated validation classes for a handful of strings.
 */
class SectionFields
{
    /**
     * section key => field definitions.
     *
     * @return array<string, array<int, FieldDefinition>>
     */
    public static function for(FormType $formType): array
    {
        return match ($formType) {
            FormType::OrganizationRegistration => [
                'contact_information' => self::contactInformation(),
                'organization_details' => self::organizationDetails(),
                'adviser_selection' => [
                    // Resolved to the adviser's NAME — a raw FK ("12 → 15")
                    // tells an approver nothing.
                    new FieldDefinition(
                        'adviser_id',
                        'Adviser',
                        'text',
                        fn (OrganizationRegistrationDetail $detail) => $detail->adviser?->name,
                    ),
                ],
            ],

            FormType::OrganizationRenewal => [
                'contact_information' => self::contactInformation(),
                'organization_details' => self::organizationDetails(),
            ],

            FormType::ActivityProposal => [
                'rso_info' => [
                    new FieldDefinition('title', 'Activity Title'),
                ],
                'activity_details' => [
                    new FieldDefinition('activity_nature', 'Nature of Activity'),
                    new FieldDefinition('activity_type', 'Type of Activity'),
                ],
                'partner_orgs_sdg' => [
                    new FieldDefinition('partner_organizations', 'Partner Organizations', 'list'),
                    new FieldDefinition('target_sdg', 'Target SDG'),
                ],
                // SectionFlags' proposal list is a deduped union of step 1
                // and step 2, so 'budget' legitimately spans both steps'
                // budget fields. Nothing in the app distinguishes them today
                // and this registry deliberately does not introduce that
                // distinction.
                'budget' => [
                    new FieldDefinition('proposed_budget', 'Proposed Budget', 'money'),
                    new FieldDefinition('budget_source', 'Budget Source'),
                    new FieldDefinition('source_of_funding', 'Source of Funding'),
                    new FieldDefinition('expense_items', 'Expense Items', 'expense_items'),
                ],
                // The ONLY section whose fields live on CalendarActivity
                // rather than ActivityProposal, and only editable at all for
                // OFF-calendar proposals — see ResubmitActivityProposal.
                'schedule_venue' => [
                    new FieldDefinition('venue', 'Venue'),
                    new FieldDefinition('activity_date', 'Date'),
                    new FieldDefinition('start_time', 'Start Time'),
                    new FieldDefinition('end_time', 'End Time'),
                ],
                'objectives' => [
                    new FieldDefinition('objectives', 'Objectives'),
                ],
                'activity_description' => [
                    new FieldDefinition('narrative', 'Narrative'),
                    new FieldDefinition('criteria_mechanics', 'Criteria / Mechanics'),
                    new FieldDefinition('program_flow', 'Program Flow'),
                ],
            ],

            FormType::AfterActivityReport => [
                'summary_program' => [
                    new FieldDefinition('summary', 'Summary'),
                    new FieldDefinition('activity_chairs', 'Activity Chairs', 'list'),
                    new FieldDefinition('prepared_by', 'Prepared By'),
                    new FieldDefinition('event_program', 'Event Program'),
                ],
                'evaluation' => [
                    new FieldDefinition('target_participants_percentage', 'Target Participants (%)'),
                    new FieldDefinition('outcomes', 'Outcomes'),
                    new FieldDefinition('participant_count', 'Participant Count'),
                ],
            ],

            FormType::ActivityCalendar => [], // dynamic — see calendarFields()
        };
    }

    /**
     * The fixed per-row template applied to every flagged "activity_{i}".
     *
     * @return array<int, FieldDefinition>
     */
    public static function calendarFields(): array
    {
        return [
            new FieldDefinition('name', 'Activity Name'),
            new FieldDefinition('venue', 'Venue'),
            new FieldDefinition('activity_date', 'Date'),
            new FieldDefinition('start_time', 'Start Time'),
            new FieldDefinition('end_time', 'End Time'),
            new FieldDefinition('sdg', 'SDG'),
            new FieldDefinition('participant_program_assigned', 'Participants / Program'),
            new FieldDefinition('budget', 'Budget', 'money'),
            new FieldDefinition('description', 'Description'),
        ];
    }

    /** @return array<int, FieldDefinition> */
    public static function definitionsFor(FormType $formType, string $sectionKey): array
    {
        return self::for($formType)[$sectionKey] ?? [];
    }

    /**
     * Flat, field-key-deduped definitions across every flagged section — the
     * list an action class snapshots off a single model in one pass.
     *
     * @param  array<int, string>  $sectionKeys
     * @return array<int, FieldDefinition>
     */
    public static function definitionsForSections(FormType $formType, array $sectionKeys): array
    {
        $registry = self::for($formType);
        $defs = [];

        foreach ($sectionKeys as $key) {
            foreach ($registry[$key] ?? [] as $def) {
                $defs[$def->key] = $def;
            }
        }

        return array_values($defs);
    }

    /** @return array<int, FieldDefinition> */
    private static function contactInformation(): array
    {
        return [
            new FieldDefinition('contact_person', 'Contact Person'),
            new FieldDefinition('contact_no', 'Contact Number'),
            new FieldDefinition('email_address', 'Email Address'),
        ];
    }

    /** @return array<int, FieldDefinition> */
    private static function organizationDetails(): array
    {
        return [
            new FieldDefinition('organization_type', 'Organization Type'),
            new FieldDefinition('date_organized', 'Date Organized'),
            new FieldDefinition('purpose_of_organization', 'Purpose of Organization'),
        ];
    }
}
