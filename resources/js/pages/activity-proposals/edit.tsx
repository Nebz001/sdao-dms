import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import FlaggedSectionWrapper from '@/components/flagged-section-wrapper';
import ImmediateAttachmentUpload from '@/components/immediate-attachment-upload';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import * as activityProposals from '@/routes/activity-proposals';

type OptionItem = { value: string; label: string };

type ProposalData = {
    calendar_mode: string;
    title: string;
    objectives: string | null;
    narrative: string | null;
    criteria_mechanics: string | null;
    program_flow: string | null;
    source_of_funding: string | null;
    expenses: string | null;
    proposed_budget: string | null;
    activity_nature: string | null;
    activity_type: string | null;
    partner_organizations: string[] | null;
    target_sdg: string | null;
    budget_source: string | null;
} | null;

type ActivityData = {
    id: number;
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
} | null;

type Props = {
    document: { id: number; title: string };
    proposal: ProposalData;
    activity: ActivityData;
    activityNatures: OptionItem[];
    activityTypes: OptionItem[];
    sdgs: OptionItem[];
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    flaggedSections: string[];
};

export default function EditActivityProposal({
    document: doc,
    proposal,
    activity,
    activityNatures,
    activityTypes,
    sdgs,
    attachmentSlots,
    attachments,
    flaggedSections,
}: Props) {
    const isOffCalendar = proposal?.calendar_mode === 'off_calendar';
    const [partnerOrgs, setPartnerOrgs] = useState<string[]>(
        proposal?.partner_organizations?.length ? proposal.partner_organizations : [''],
    );

    return (
        <>
            <Head title={`Edit — ${doc.title}`} />

            <div className="mx-auto max-w-xl space-y-6 p-8">
                <h1 className="text-xl font-semibold">Edit Proposal</h1>
                <p className="text-sm text-muted-foreground">{doc.title}</p>

                {flaggedSections.includes('general') && (
                    <div className="rounded-md border border-destructive/60 bg-destructive/10 p-3 text-sm text-destructive">
                        General revisions requested — see the reviewer's comment in Revision History below.
                    </div>
                )}

                {activity && (
                    <FlaggedSectionWrapper sectionKey="schedule_venue" flagged={flaggedSections}>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Current Activity</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p className="font-medium">{activity.name}</p>
                            <p className="text-muted-foreground">
                                {activity.venue} · {activity.activity_date} · {activity.start_time}–{activity.end_time}
                            </p>
                        </CardContent>
                    </Card>
                    </FlaggedSectionWrapper>
                )}

                <Form action={activityProposals.update({ document: doc.id }).url} method="put">
                    {({ processing, errors }) => (
                    <div className="space-y-4">
                        {/* Off-calendar: allow editing activity details */}
                        {isOffCalendar && (
                            <>
                                <FlaggedSectionWrapper sectionKey="rso_info" flagged={flaggedSections}>
                                <div className="space-y-1">
                                    <Label htmlFor="title">Title of Activity</Label>
                                    <Input id="title" name="title" defaultValue={proposal?.title ?? ''} />
                                    <InputError message={errors.title} />
                                </div>
                                </FlaggedSectionWrapper>
                                <FlaggedSectionWrapper sectionKey="schedule_venue" flagged={flaggedSections}>
                                <div className="space-y-4">
                                <div className="space-y-1">
                                    <Label htmlFor="venue">Venue</Label>
                                    <Input id="venue" name="venue" defaultValue={activity?.venue ?? ''} />
                                    <InputError message={errors.venue} />
                                </div>
                                <div className="grid grid-cols-3 gap-3">
                                    <div className="space-y-1">
                                        <Label htmlFor="activity_date">Date of Activity</Label>
                                        <Input
                                            id="activity_date"
                                            name="activity_date"
                                            type="date"
                                            defaultValue={activity?.activity_date ?? ''}
                                        />
                                        <InputError message={errors.activity_date} />
                                    </div>
                                    <div className="space-y-1">
                                        <Label htmlFor="start_time">Start</Label>
                                        <Input
                                            id="start_time"
                                            name="start_time"
                                            type="time"
                                            defaultValue={activity?.start_time ?? ''}
                                        />
                                        <InputError message={errors.start_time} />
                                    </div>
                                    <div className="space-y-1">
                                        <Label htmlFor="end_time">End</Label>
                                        <Input
                                            id="end_time"
                                            name="end_time"
                                            type="time"
                                            defaultValue={activity?.end_time ?? ''}
                                        />
                                        <InputError message={errors.end_time} />
                                    </div>
                                </div>
                                </div>
                                </FlaggedSectionWrapper>
                            </>
                        )}

                        <FlaggedSectionWrapper sectionKey="objectives" flagged={flaggedSections}>
                        <div className="space-y-1">
                            <Label htmlFor="objectives">Objectives</Label>
                            <Textarea
                                id="objectives"
                                name="objectives"
                                defaultValue={proposal?.objectives ?? ''}
                                rows={4}
                            />
                            <InputError message={errors.objectives} />
                        </div>
                        </FlaggedSectionWrapper>

                        <FlaggedSectionWrapper sectionKey="activity_description" flagged={flaggedSections}>
                        <div className="space-y-4">
                        <div className="space-y-1">
                            <Label htmlFor="narrative">Narrative / Description</Label>
                            <Textarea
                                id="narrative"
                                name="narrative"
                                defaultValue={proposal?.narrative ?? ''}
                                rows={6}
                            />
                            <InputError message={errors.narrative} />
                        </div>

                        {/* Exact field corrections (Phase 2 item 7 slice 4b). */}
                        <div className="space-y-1">
                            <Label htmlFor="criteria_mechanics">Criteria/Mechanics</Label>
                            <Textarea
                                id="criteria_mechanics"
                                name="criteria_mechanics"
                                defaultValue={proposal?.criteria_mechanics ?? ''}
                                rows={4}
                            />
                            <InputError message={errors.criteria_mechanics} />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="program_flow">Program Flow</Label>
                            <Textarea
                                id="program_flow"
                                name="program_flow"
                                defaultValue={proposal?.program_flow ?? ''}
                                rows={4}
                            />
                            <InputError message={errors.program_flow} />
                        </div>
                        </div>
                        </FlaggedSectionWrapper>

                        <FlaggedSectionWrapper sectionKey="budget" flagged={flaggedSections}>
                        <div className="space-y-4">
                        <div className="space-y-1">
                            <Label htmlFor="source_of_funding">Source of Funding</Label>
                            <Textarea
                                id="source_of_funding"
                                name="source_of_funding"
                                defaultValue={proposal?.source_of_funding ?? ''}
                                rows={3}
                            />
                            <InputError message={errors.source_of_funding} />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="expenses">Expenses</Label>
                            <Textarea
                                id="expenses"
                                name="expenses"
                                defaultValue={proposal?.expenses ?? ''}
                                rows={4}
                            />
                            <InputError message={errors.expenses} />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="proposed_budget">Proposed Budget</Label>
                            <Input
                                id="proposed_budget"
                                name="proposed_budget"
                                type="number"
                                min="0"
                                step="0.01"
                                defaultValue={proposal?.proposed_budget ?? ''}
                            />
                            <InputError message={errors.proposed_budget} />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="budget_source">Budget Source</Label>
                            <Input
                                id="budget_source"
                                name="budget_source"
                                defaultValue={proposal?.budget_source ?? ''}
                                placeholder="e.g. Org funds, sponsorship…"
                            />
                            <InputError message={errors.budget_source} />
                        </div>
                        </div>
                        </FlaggedSectionWrapper>

                        <FlaggedSectionWrapper sectionKey="activity_details" flagged={flaggedSections}>
                        <div className="space-y-4">
                        <div className="space-y-1">
                            <Label htmlFor="activity_nature">Nature of Activity</Label>
                            <Select name="activity_nature" defaultValue={proposal?.activity_nature ?? undefined}>
                                <SelectTrigger id="activity_nature">
                                    <SelectValue placeholder="Select nature…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {activityNatures.map((n) => (
                                        <SelectItem key={n.value} value={n.value}>
                                            {n.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.activity_nature} />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="activity_type">Type of Activity</Label>
                            <Select name="activity_type" defaultValue={proposal?.activity_type ?? undefined}>
                                <SelectTrigger id="activity_type">
                                    <SelectValue placeholder="Select type…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {activityTypes.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.activity_type} />
                        </div>
                        </div>
                        </FlaggedSectionWrapper>

                        <FlaggedSectionWrapper sectionKey="partner_orgs_sdg" flagged={flaggedSections}>
                        <div className="space-y-4">
                        <div className="space-y-1">
                            <div className="flex items-center justify-between">
                                <Label>Partner Organization(s)/School(s)/RSO</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setPartnerOrgs((prev) => [...prev, ''])}
                                >
                                    + Add
                                </Button>
                            </div>
                            {partnerOrgs.map((org, i) => (
                                <div key={i} className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <Input
                                            name={`partner_organizations[${i}]`}
                                            value={org}
                                            onChange={(e) =>
                                                setPartnerOrgs((prev) => {
                                                    const next = [...prev];
                                                    next[i] = e.target.value;

                                                    return next;
                                                })
                                            }
                                            placeholder="Organization, School, or RSO name"
                                        />
                                        {partnerOrgs.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setPartnerOrgs((prev) => prev.filter((_, idx) => idx !== i))}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                    <InputError message={errors[`partner_organizations.${i}`]} />
                                </div>
                            ))}
                            <InputError message={errors.partner_organizations} />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="target_sdg">Target SDG</Label>
                            <Select name="target_sdg" defaultValue={proposal?.target_sdg ?? undefined}>
                                <SelectTrigger id="target_sdg">
                                    <SelectValue placeholder="Select SDG…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {sdgs.map((s) => (
                                        <SelectItem key={s.value} value={s.value}>
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.target_sdg} />
                        </div>
                        </div>
                        </FlaggedSectionWrapper>

                        <FlaggedSectionWrapper sectionKey="resource_person" flagged={flaggedSections}>
                        <div className="space-y-4">
                        {attachmentSlots.map((slot) => (
                            <ImmediateAttachmentUpload
                                key={slot.key}
                                documentId={doc.id}
                                slot={slot}
                                existing={attachments[slot.key]?.[0] ?? null}
                            />
                        ))}
                        </div>
                        </FlaggedSectionWrapper>

                        <InputError message={errors.activity} />

                        <Button type="submit" loading={processing} loadingText="Resubmitting…" className="w-full">
                            Resubmit for Review
                        </Button>
                    </div>
                    )}
                </Form>
            </div>
        </>
    );
}

EditActivityProposal.layout = {
    breadcrumbs: [
        { title: 'Activity Proposals', href: '/activity-proposals' },
        { title: 'Edit' },
    ],
};
