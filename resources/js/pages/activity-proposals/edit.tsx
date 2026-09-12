import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import AttachmentSlotField from '@/components/attachment-slot-field';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import FlaggedSectionWrapper from '@/components/flagged-section-wrapper';
import InputError from '@/components/input-error';
import SdgCheckboxGroup from '@/components/sdg-checkbox-group';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { todayDateString } from '@/lib/utils';
import * as activityProposals from '@/routes/activity-proposals';
import type { FlaggedRevisionProps } from '@/types';

type OptionItem = { value: string; label: string };

type ExpenseItem = { material: string; quantity: string; unit_price: string };

type ProposalData = {
    calendar_mode: string;
    title: string;
    overall_goal: string | null;
    specific_objectives: string | null;
    criteria_mechanics: string | null;
    program_flow: string | null;
    expenses: string | null;
    expense_items: ExpenseItem[] | null;
    responsible_persons: string[] | null;
    proposed_budget: string | null;
    activity_nature: string | null;
    activity_nature_other: string | null;
    activity_type: string | null;
    activity_type_other: string | null;
    partner_organizations: string[] | null;
    target_sdg: string[];
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

function rowTotal(item: ExpenseItem): number {
    return (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
}

function money(amount: number): string {
    return amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

type Props = {
    document: { id: number; title: string };
    proposal: ProposalData;
    activity: ActivityData;
    activityNatures: OptionItem[];
    activityTypes: OptionItem[];
    sdgs: OptionItem[];
    budgetSources: OptionItem[];
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
} & FlaggedRevisionProps;

export default function EditActivityProposal({
    document: doc,
    proposal,
    activity,
    activityNatures,
    activityTypes,
    sdgs,
    budgetSources,
    attachmentSlots,
    attachments,
    flaggedSections,
    flaggedComment,
    flaggedSectionComments,
}: Props) {
    const minDate = todayDateString();
    const isOffCalendar = proposal?.calendar_mode === 'off_calendar';
    const [partnerOrgs, setPartnerOrgs] = useState<string[]>(
        proposal?.partner_organizations?.length ? proposal.partner_organizations : [''],
    );
    const [targetSdg, setTargetSdg] = useState<string[]>(proposal?.target_sdg ?? []);
    const [expenseItems, setExpenseItems] = useState<ExpenseItem[]>(
        proposal?.expense_items?.length ? proposal.expense_items : [{ material: '', quantity: '', unit_price: '' }],
    );
    const expenseTotal = expenseItems.reduce((sum, item) => sum + rowTotal(item), 0);
    const [responsiblePersons, setResponsiblePersons] = useState<string[]>(
        proposal?.responsible_persons?.length ? proposal.responsible_persons : [''],
    );

    // Nature/Type of Activity — controlled so the "Others" conditional
    // specify-field (Group B item 5) can key off the current selection.
    const [activityNature, setActivityNature] = useState(proposal?.activity_nature ?? '');
    const [activityNatureOther, setActivityNatureOther] = useState(proposal?.activity_nature_other ?? '');
    const [activityType, setActivityType] = useState(proposal?.activity_type ?? '');
    const [activityTypeOther, setActivityTypeOther] = useState(proposal?.activity_type_other ?? '');

    return (
        <>
            <Head title={`Edit — ${doc.title}`} />

            <div className="max-w-xl space-y-6">
                <h1 className="text-xl font-semibold">Edit Proposal</h1>
                <p className="text-sm text-muted-foreground">{doc.title}</p>

                {flaggedSections.includes('general') && (
                    <div className="rounded-md border border-destructive/60 bg-destructive/10 p-3 text-sm text-destructive">
                        <p className="font-medium">General revisions requested</p>
                        {flaggedSectionComments.general && <p className="mt-1">{flaggedSectionComments.general}</p>}
                        {flaggedComment && <p className="mt-1 text-destructive/80">{flaggedComment}</p>}
                    </div>
                )}

                {activity && (
                    <FlaggedSectionWrapper
                                sectionKey="schedule_venue"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.schedule_venue}
                            >
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
                                <FlaggedSectionWrapper
                                sectionKey="rso_info"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.rso_info}
                            >
                                <div className="space-y-1">
                                    <Label htmlFor="title">Title of Activity</Label>
                                    <Input id="title" name="title" defaultValue={proposal?.title ?? ''} />
                                    <InputError message={errors.title} />
                                </div>
                                </FlaggedSectionWrapper>
                                <FlaggedSectionWrapper
                                sectionKey="schedule_venue"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.schedule_venue}
                            >
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
                                            min={minDate}
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

                        <FlaggedSectionWrapper
                                sectionKey="objectives"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.objectives}
                            >
                        <div className="space-y-4">
                        <div className="space-y-1">
                            <Label htmlFor="overall_goal">Overall Goal</Label>
                            <Textarea
                                id="overall_goal"
                                name="overall_goal"
                                defaultValue={proposal?.overall_goal ?? ''}
                                rows={3}
                            />
                            <InputError message={errors.overall_goal} />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="specific_objectives">Specific Objectives</Label>
                            <Textarea
                                id="specific_objectives"
                                name="specific_objectives"
                                defaultValue={proposal?.specific_objectives ?? ''}
                                rows={4}
                            />
                            <InputError message={errors.specific_objectives} />
                        </div>
                        </div>
                        </FlaggedSectionWrapper>

                        <FlaggedSectionWrapper
                                sectionKey="activity_description"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.activity_description}
                            >
                        <div className="space-y-4">
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

                        <FlaggedSectionWrapper
                                sectionKey="responsible_persons"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.responsible_persons}
                            >
                        <div className="space-y-1">
                            <div className="flex items-center justify-between">
                                <Label>Responsible Person(s)</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setResponsiblePersons((prev) => [...prev, ''])}
                                >
                                    + Add
                                </Button>
                            </div>
                            {responsiblePersons.map((name, i) => (
                                <div key={i} className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <Input
                                            name={`responsible_persons[${i}]`}
                                            value={name}
                                            onChange={(e) =>
                                                setResponsiblePersons((prev) => {
                                                    const next = [...prev];
                                                    next[i] = e.target.value;

                                                    return next;
                                                })
                                            }
                                            placeholder="Full name"
                                        />
                                        {responsiblePersons.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setResponsiblePersons((prev) => prev.filter((_, idx) => idx !== i))}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                    <InputError message={errors[`responsible_persons.${i}`]} />
                                </div>
                            ))}
                            <InputError message={errors.responsible_persons} />
                        </div>
                        </FlaggedSectionWrapper>

                        <FlaggedSectionWrapper
                                sectionKey="budget"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.budget}
                            >
                        <div className="space-y-4">
                        <div className="space-y-1">
                            <div className="flex items-center justify-between">
                                <Label>Expenses</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setExpenseItems((prev) => [...prev, { material: '', quantity: '', unit_price: '' }])}
                                >
                                    + Add Item
                                </Button>
                            </div>
                            {proposal?.expenses && (
                                <p className="rounded-md border border-dashed bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                                    Previously entered as text — re-enter it below as itemized rows: “{proposal.expenses}”
                                </p>
                            )}
                            {expenseItems.map((item, i) => (
                                <div key={i} className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <Input
                                            name={`expense_items[${i}][material]`}
                                            value={item.material}
                                            onChange={(e) =>
                                                setExpenseItems((prev) => {
                                                    const next = [...prev];
                                                    next[i] = { ...next[i], material: e.target.value };

                                                    return next;
                                                })
                                            }
                                            placeholder="Material (e.g. Tarpaulin)"
                                            className="flex-1"
                                        />
                                        <Input
                                            name={`expense_items[${i}][quantity]`}
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.quantity}
                                            onChange={(e) =>
                                                setExpenseItems((prev) => {
                                                    const next = [...prev];
                                                    next[i] = { ...next[i], quantity: e.target.value };

                                                    return next;
                                                })
                                            }
                                            placeholder="Qty"
                                            className="w-20"
                                        />
                                        <Input
                                            name={`expense_items[${i}][unit_price]`}
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.unit_price}
                                            onChange={(e) =>
                                                setExpenseItems((prev) => {
                                                    const next = [...prev];
                                                    next[i] = { ...next[i], unit_price: e.target.value };

                                                    return next;
                                                })
                                            }
                                            placeholder="Unit price"
                                            className="w-28"
                                        />
                                        {expenseItems.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setExpenseItems((prev) => prev.filter((_, idx) => idx !== i))}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                    <div className="flex justify-end text-xs text-muted-foreground">
                                        Line total: ₱{money(rowTotal(item))}
                                    </div>
                                    <InputError
                                        message={
                                            errors[`expense_items.${i}.material`] ??
                                            errors[`expense_items.${i}.quantity`] ??
                                            errors[`expense_items.${i}.unit_price`]
                                        }
                                    />
                                </div>
                            ))}
                            <div className="flex items-center justify-end gap-2 border-t pt-2 text-sm">
                                <span className="font-medium text-muted-foreground">Total</span>
                                <span className="font-semibold tabular-nums">₱{money(expenseTotal)}</span>
                            </div>
                            <InputError message={errors.expense_items} />
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
                            <Select name="budget_source" defaultValue={proposal?.budget_source ?? undefined}>
                                <SelectTrigger id="budget_source">
                                    <SelectValue placeholder="Select source…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {budgetSources.map((b) => (
                                        <SelectItem key={b.value} value={b.value}>
                                            {b.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.budget_source} />
                        </div>
                        </div>
                        </FlaggedSectionWrapper>

                        <FlaggedSectionWrapper
                                sectionKey="activity_details"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.activity_details}
                            >
                        <div className="space-y-4">
                        <div className="space-y-1">
                            <Label htmlFor="activity_nature">Nature of Activity</Label>
                            <Select name="activity_nature" value={activityNature} onValueChange={setActivityNature}>
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
                            {activityNature === 'others' && (
                                <div className="space-y-1 pt-1">
                                    <Label htmlFor="activity_nature_other">Please specify</Label>
                                    <Input
                                        id="activity_nature_other"
                                        name="activity_nature_other"
                                        value={activityNatureOther}
                                        onChange={(e) => setActivityNatureOther(e.target.value)}
                                    />
                                    <InputError message={errors.activity_nature_other} />
                                </div>
                            )}
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="activity_type">Type of Activity</Label>
                            <Select name="activity_type" value={activityType} onValueChange={setActivityType}>
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
                            {activityType === 'others' && (
                                <div className="space-y-1 pt-1">
                                    <Label htmlFor="activity_type_other">Please specify</Label>
                                    <Input
                                        id="activity_type_other"
                                        name="activity_type_other"
                                        value={activityTypeOther}
                                        onChange={(e) => setActivityTypeOther(e.target.value)}
                                    />
                                    <InputError message={errors.activity_type_other} />
                                </div>
                            )}
                        </div>
                        </div>
                        </FlaggedSectionWrapper>

                        <FlaggedSectionWrapper
                                sectionKey="partner_orgs_sdg"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.partner_orgs_sdg}
                            >
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
                            <Label>Target SDG (select one or more)</Label>
                            <SdgCheckboxGroup
                                idPrefix="target-sdg"
                                name="target_sdg[]"
                                options={sdgs}
                                selected={targetSdg}
                                onChange={setTargetSdg}
                            />
                            <InputError message={errors.target_sdg} />
                        </div>
                        </div>
                        </FlaggedSectionWrapper>

                        {/* Step-1 attachments (Group C item 3) — now Mode A
                            (bundled into this same resubmit PUT), one
                            FlaggedSectionWrapper per slot, same pattern as
                            registrations/edit.tsx. */}
                        {attachmentSlots.map((slot) => (
                            <FlaggedSectionWrapper
                                key={slot.key}
                                sectionKey={slot.key}
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments[slot.key]}
                            >
                                <AttachmentSlotField
                                    slot={slot}
                                    existing={attachments[slot.key]}
                                    error={errors[`attachments.${slot.key}`]}
                                />
                            </FlaggedSectionWrapper>
                        ))}

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
