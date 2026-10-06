import ExpenseItemsTable from '@/components/expense-items-table';
import type { PartnerOrganization } from '@/components/partner-organizations-field';
import TagBadge from '@/components/tag-badge';
import { formatTimeRange } from '@/lib/utils';
import { DetailField, DetailSection, DetailText, DetailsCard } from './details';
import { formatLongDate, formatPeso } from './format';
import {
    CriteriaMechanicsSection,
    NarrativeSection,
    ObjectivesSection,
    Paragraphs,
    ResponsiblePersonsSection,
} from './proposal-text';

export type ProposalData = {
    calendar_mode: string;
    title: string;
    objectives: string | null;
    activity_description: string | null;
    criteria_mechanics: string | null;
    program_flow: string | null;
    expenses: string | null;
    expense_items: { material: string; quantity: string; unit_price: string }[] | null;
    expense_items_total: string | null;
    responsible_persons: string[] | null;
    proposed_budget: string | null;
    activity_nature_label: string | null;
    activity_type_label: string | null;
    partner_organizations: PartnerOrganization[] | null;
    target_sdg_labels: string[];
    budget_source_label: string | null;
} | null;

export type ProposalActivity = {
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
} | null;

/** The details card for an activity proposal, shared by the student and reviewer pages. */
export default function ProposalDetails({
    organizationName,
    proposal,
    activity,
}: {
    organizationName: string;
    proposal: ProposalData;
    activity: ProposalActivity;
}) {
    const partners = proposal?.partner_organizations ?? [];
    const persons = proposal?.responsible_persons ?? [];
    const hasNarrative =
        proposal && (proposal.objectives || proposal.activity_description || proposal.criteria_mechanics || proposal.program_flow);

    return (
        <DetailsCard title="Proposal details">
            <DetailSection label="Activity">
                <DetailField label="Activity name">{activity?.name ?? proposal?.title}</DetailField>
                <DetailField label="Calendar">
                    {proposal ? (proposal.calendar_mode === 'on_calendar' ? 'On calendar' : 'Off calendar') : null}
                </DetailField>
                <DetailField label="Venue">{activity?.venue}</DetailField>
                <DetailField label="Date">{formatLongDate(activity?.activity_date)}</DetailField>
                <DetailField label="Time">
                    {activity ? formatTimeRange(activity.start_time, activity.end_time) : null}
                </DetailField>
            </DetailSection>

            <DetailSection label="Request form">
                <DetailField label="Name of RSO">{organizationName}</DetailField>
                <DetailField label="Nature of activity" hideIfEmpty>
                    {proposal?.activity_nature_label}
                </DetailField>
                <DetailField label="Type of activity" hideIfEmpty>
                    {proposal?.activity_type_label}
                </DetailField>
                <DetailField label="Target SDG" hideIfEmpty>
                    {proposal && proposal.target_sdg_labels.length > 0 ? proposal.target_sdg_labels.join(', ') : null}
                </DetailField>
                <DetailField label="Proposed budget" hideIfEmpty>
                    {formatPeso(proposal?.proposed_budget)}
                </DetailField>
                <DetailField label="Budget source" hideIfEmpty>
                    {proposal?.budget_source_label}
                </DetailField>
                <DetailField label="Partner organization(s)/school(s)/RSO" wide hideIfEmpty>
                    {partners.length > 0 ? (
                        <ul className="flex flex-col gap-1 font-normal">
                            {partners.map((partner, i) => (
                                <li key={i} className="flex flex-wrap items-center gap-2">
                                    {partner.name}
                                    {partner.organization_id !== null && <TagBadge>Linked</TagBadge>}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </DetailField>
            </DetailSection>

            {proposal && (hasNarrative || persons.length > 0 || proposal.expense_items?.length || proposal.expenses) ? (
                <section aria-label="Narrative" className="flex flex-col gap-5">
                    <h3 className="text-xs font-medium tracking-wide text-primary-text uppercase">Narrative</h3>
                    {proposal.objectives && <ObjectivesSection text={proposal.objectives} />}
                    {proposal.activity_description && (
                        <NarrativeSection label="Activity description" labelId="proposal-activity-description">
                            <Paragraphs text={proposal.activity_description} />
                        </NarrativeSection>
                    )}
                    {proposal.criteria_mechanics && <CriteriaMechanicsSection text={proposal.criteria_mechanics} />}
                    {proposal.program_flow && (
                        <NarrativeSection label="Program flow" labelId="proposal-program-flow">
                            <Paragraphs text={proposal.program_flow} />
                        </NarrativeSection>
                    )}
                    <ExpenseItemsTable
                        items={proposal.expense_items}
                        total={proposal.expense_items_total}
                        legacyText={proposal.expenses}
                    />
                    <ResponsiblePersonsSection entries={proposal.responsible_persons} />
                </section>
            ) : (
                <DetailText label="Narrative" />
            )}
        </DetailsCard>
    );
}
