import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ActivityCard } from '@/components/activity-picker';
import AttachmentSlotField from '@/components/attachment-slot-field';
import type {
    AttachmentSlotDef,
    ExistingAttachment,
} from '@/components/attachment-slot-field';
import { formatLongDate } from '@/components/document-view/format';
import {
    FixFooter,
    FixItemBox,
    FixProvider,
    FixShell,
    FixSection,
    FixStrip,
    FixSummary,
    FlaggedFile,
    LockedField,
    LockedGrid,
    LockedRequirements,
    fileBadges,
    lockedSummary,
    useFix,
} from '@/components/fix-resend';
import { FocusFirstError, FormCard, FormFooter } from '@/components/form-shell';
import FormSubmitConfirm from '@/components/form-submit-confirm';
import InputError from '@/components/input-error';
import PartnerOrganizationsField from '@/components/partner-organizations-field';
import type { PartnerOrganization } from '@/components/partner-organizations-field';
import SdgCheckboxGroup from '@/components/sdg-checkbox-group';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { hasChanged } from '@/lib/fix-resend';
import type { FixProps } from '@/lib/fix-resend';
import { todayDateString } from '@/lib/utils';
import * as activityProposals from '@/routes/activity-proposals';

type OptionItem = { value: string; label: string };

type ExpenseItem = { material: string; quantity: string; unit_price: string };

type ProposalData = {
    calendar_mode: string;
    title: string;
    objectives: string | null;
    activity_description: string | null;
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
    partner_organizations: PartnerOrganization[] | null;
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
    return (
        (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0)
    );
}

function money(amount: number): string {
    return amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
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
    fix: FixProps;
};

const BLANK_EXPENSE: ExpenseItem = {
    material: '',
    quantity: '',
    unit_price: '',
};

export default function EditActivityProposal({ fix, ...props }: Props) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Activity Proposals' },
                { title: 'Fix and Resend' },
            ],
            trailParent: { title: fix.back.title, href: fix.back.href },
        });
    }, [fix.back.title, fix.back.href]);

    return (
        <FixProvider fix={fix}>
            <Head title="Fix and Resend" />
            <ProposalFix {...props} />
        </FixProvider>
    );
}

function ProposalFix({
    document: doc,
    proposal,
    activity,
    activityNatures,
    activityTypes,
    sdgs,
    budgetSources,
    attachmentSlots,
    attachments,
}: Omit<Props, 'fix'>) {
    const { fix, isFlagged } = useFix();
    const formId = `edit-proposal-form-${doc.id}`;
    const minDate = todayDateString();
    const isOffCalendar = proposal?.calendar_mode === 'off_calendar';
    const draft = (key: string) =>
        (fix.draft?.[key] ?? {}) as Record<string, unknown>;
    const label = (options: OptionItem[], value: string | null) =>
        options.find((o) => o.value === value)?.label ?? value;

    // ── The returned version, per flaggable section, and what is on screen now. ──
    const base = {
        rso_info: { title: proposal?.title ?? '' },
        activity_details: {
            activity_nature: proposal?.activity_nature ?? '',
            activity_nature_other: proposal?.activity_nature_other ?? '',
            activity_type: proposal?.activity_type ?? '',
            activity_type_other: proposal?.activity_type_other ?? '',
        },
        partner_orgs_sdg: {
            partner_organizations: (proposal?.partner_organizations ?? []).map(
                (p) => p.name,
            ),
            target_sdg: proposal?.target_sdg ?? [],
        },
        budget: {
            proposed_budget: proposal?.proposed_budget ?? '',
            budget_source: proposal?.budget_source ?? '',
            expense_items: (proposal?.expense_items ?? []).filter(
                (item) => item.material.trim() !== '',
            ),
        },
        schedule_venue: {
            venue: activity?.venue ?? '',
            activity_date: activity?.activity_date ?? '',
            start_time: activity?.start_time ?? '',
            end_time: activity?.end_time ?? '',
        },
        objectives: { objectives: proposal?.objectives ?? '' },
        activity_description: {
            activity_description: proposal?.activity_description ?? '',
            criteria_mechanics: proposal?.criteria_mechanics ?? '',
            program_flow: proposal?.program_flow ?? '',
        },
        responsible_persons: {
            responsible_persons: (proposal?.responsible_persons ?? []).filter(
                (p) => p.trim() !== '',
            ),
        },
    };

    const [title, setTitle] = useState(
        String(draft('rso_info').title ?? base.rso_info.title),
    );
    const [nature, setNature] = useState(
        String(
            draft('activity_details').activity_nature ??
                base.activity_details.activity_nature,
        ),
    );
    const [natureOther, setNatureOther] = useState(
        String(
            draft('activity_details').activity_nature_other ??
                base.activity_details.activity_nature_other,
        ),
    );
    const [type, setType] = useState(
        String(
            draft('activity_details').activity_type ??
                base.activity_details.activity_type,
        ),
    );
    const [typeOther, setTypeOther] = useState(
        String(
            draft('activity_details').activity_type_other ??
                base.activity_details.activity_type_other,
        ),
    );
    const [partners, setPartners] = useState<PartnerOrganization[]>(
        (draft('partner_orgs_sdg').partner_organizations as
            | PartnerOrganization[]
            | undefined) ??
            proposal?.partner_organizations ??
            [],
    );
    const [targetSdg, setTargetSdg] = useState<string[]>(
        (draft('partner_orgs_sdg').target_sdg as string[] | undefined) ??
            base.partner_orgs_sdg.target_sdg,
    );
    const [expenseItems, setExpenseItems] = useState<ExpenseItem[]>(
        (draft('budget').expense_items as ExpenseItem[] | undefined) ??
            (proposal?.expense_items?.length
                ? proposal.expense_items
                : [BLANK_EXPENSE]),
    );
    const [proposedBudget, setProposedBudget] = useState(
        String(draft('budget').proposed_budget ?? base.budget.proposed_budget),
    );
    const [budgetSource, setBudgetSource] = useState(
        String(draft('budget').budget_source ?? base.budget.budget_source),
    );
    const [schedule, setSchedule] = useState({
        ...base.schedule_venue,
        ...(draft('schedule_venue') as Partial<typeof base.schedule_venue>),
    });
    const [objectives, setObjectives] = useState(
        String(draft('objectives').objectives ?? base.objectives.objectives),
    );
    const [description, setDescription] = useState({
        ...base.activity_description,
        ...(draft('activity_description') as Partial<
            typeof base.activity_description
        >),
    });
    const [responsible, setResponsible] = useState<string[]>(
        (draft('responsible_persons').responsible_persons as
            | string[]
            | undefined) ??
            (proposal?.responsible_persons?.length
                ? proposal.responsible_persons
                : ['']),
    );
    const [files, setFiles] = useState<Record<string, File[]>>({});

    const current = {
        rso_info: { title },
        activity_details: {
            activity_nature: nature,
            activity_nature_other: natureOther,
            activity_type: type,
            activity_type_other: typeOther,
        },
        partner_orgs_sdg: {
            partner_organizations: partners
                .map((p) => p.name)
                .filter((name) => name.trim() !== ''),
            target_sdg: targetSdg,
        },
        budget: {
            proposed_budget: proposedBudget,
            budget_source: budgetSource,
            expense_items: expenseItems.filter(
                (item) => item.material.trim() !== '',
            ),
        },
        schedule_venue: schedule,
        objectives: { objectives },
        activity_description: description,
        responsible_persons: {
            responsible_persons: responsible.filter((p) => p.trim() !== ''),
        },
    };
    const changed = (key: keyof typeof base) =>
        hasChanged(base[key], current[key]);

    const expenseTotal = expenseItems.reduce(
        (sum, item) => sum + rowTotal(item),
        0,
    );
    const flaggedKeys = fix.items.map((item) => item.key);
    const flaggedSlots = attachmentSlots.filter((slot) =>
        flaggedKeys.includes(slot.key),
    );
    const lockedSlots = attachmentSlots.filter(
        (slot) => !flaggedKeys.includes(slot.key),
    );

    return (
        <FixShell fix={fix}>
            <Form
                action={activityProposals.update({ document: doc.id }).url}
                method="put"
                id={formId}
            >
                {({ processing, errors }) => (
                    <FormCard>
                        <FocusFirstError errors={errors} />
                        <FixStrip />
                        <FixSummary />
                        {(errors.locked || errors.flagged) && (
                            <p
                                role="alert"
                                className="border-b bg-destructive/10 px-5 py-3 text-sm text-destructive-foreground sm:px-6"
                            >
                                {errors.locked} {errors.flagged}
                            </p>
                        )}

                        {/* ── Step 1: the request form ───────────────────────────── */}
                        <FixSection
                            title="Activity request"
                            itemKeys={[
                                'rso_info',
                                'activity_details',
                                'partner_orgs_sdg',
                            ]}
                        >
                            {/* Which calendar the activity is on, and the picked activity, are fixed once filed. */}
                            <LockedGrid>
                                <LockedField
                                    label="Calendar"
                                    value={
                                        isOffCalendar
                                            ? 'Off-calendar activity'
                                            : 'On the approved activity calendar'
                                    }
                                />
                                {!isOffCalendar && activity && (
                                    <LockedField
                                        label="Calendar activity"
                                        value={activity.name}
                                    />
                                )}
                            </LockedGrid>

                            {(fix.active || isOffCalendar) && (
                                <FixItemBox
                                    sectionKey="rso_info"
                                    changed={changed('rso_info')}
                                    locked={
                                        <LockedGrid>
                                            <LockedField
                                                label="Title of activity"
                                                value={base.rso_info.title}
                                            />
                                        </LockedGrid>
                                    }
                                >
                                    <div className="space-y-1">
                                        <Label htmlFor="title">
                                            Title of Activity
                                        </Label>
                                        <Input
                                            id="title"
                                            name="title"
                                            value={title}
                                            onChange={(e) =>
                                                setTitle(e.target.value)
                                            }
                                        />
                                        <InputError message={errors.title} />
                                    </div>
                                </FixItemBox>
                            )}

                            <FixItemBox
                                sectionKey="activity_details"
                                changed={changed('activity_details')}
                                locked={
                                    <LockedGrid>
                                        <LockedField
                                            label="Nature of activity"
                                            value={[
                                                label(
                                                    activityNatures,
                                                    base.activity_details
                                                        .activity_nature,
                                                ),
                                                base.activity_details
                                                    .activity_nature_other,
                                            ]
                                                .filter(Boolean)
                                                .join(': ')}
                                        />
                                        <LockedField
                                            label="Type of activity"
                                            value={[
                                                label(
                                                    activityTypes,
                                                    base.activity_details
                                                        .activity_type,
                                                ),
                                                base.activity_details
                                                    .activity_type_other,
                                            ]
                                                .filter(Boolean)
                                                .join(': ')}
                                        />
                                    </LockedGrid>
                                }
                            >
                                <div className="space-y-4">
                                    <div className="space-y-1">
                                        <Label htmlFor="activity_nature">
                                            Nature of Activity
                                        </Label>
                                        <Select
                                            name="activity_nature"
                                            value={nature}
                                            onValueChange={setNature}
                                        >
                                            <SelectTrigger id="activity_nature">
                                                <SelectValue placeholder="Select nature…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {activityNatures.map((n) => (
                                                    <SelectItem
                                                        key={n.value}
                                                        value={n.value}
                                                    >
                                                        {n.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.activity_nature}
                                        />
                                        {nature === 'others' && (
                                            <div className="space-y-1 pt-1">
                                                <Label htmlFor="activity_nature_other">
                                                    Please specify
                                                </Label>
                                                <Input
                                                    id="activity_nature_other"
                                                    name="activity_nature_other"
                                                    value={natureOther}
                                                    onChange={(e) =>
                                                        setNatureOther(
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.activity_nature_other
                                                    }
                                                />
                                            </div>
                                        )}
                                    </div>
                                    <div className="space-y-1">
                                        <Label htmlFor="activity_type">
                                            Type of Activity
                                        </Label>
                                        <Select
                                            name="activity_type"
                                            value={type}
                                            onValueChange={setType}
                                        >
                                            <SelectTrigger id="activity_type">
                                                <SelectValue placeholder="Select type…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {activityTypes.map((t) => (
                                                    <SelectItem
                                                        key={t.value}
                                                        value={t.value}
                                                    >
                                                        {t.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.activity_type}
                                        />
                                        {type === 'others' && (
                                            <div className="space-y-1 pt-1">
                                                <Label htmlFor="activity_type_other">
                                                    Please specify
                                                </Label>
                                                <Input
                                                    id="activity_type_other"
                                                    name="activity_type_other"
                                                    value={typeOther}
                                                    onChange={(e) =>
                                                        setTypeOther(
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.activity_type_other
                                                    }
                                                />
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </FixItemBox>

                            <FixItemBox
                                sectionKey="partner_orgs_sdg"
                                changed={changed('partner_orgs_sdg')}
                                locked={
                                    <LockedGrid>
                                        <LockedField
                                            label="Partner organization(s)"
                                            value={base.partner_orgs_sdg.partner_organizations.join(
                                                ', ',
                                            )}
                                        />
                                        <LockedField
                                            label="Target SDG"
                                            value={sdgs
                                                .filter((s) =>
                                                    base.partner_orgs_sdg.target_sdg.includes(
                                                        s.value,
                                                    ),
                                                )
                                                .map((s) => s.label)
                                                .join(', ')}
                                        />
                                    </LockedGrid>
                                }
                            >
                                <div className="space-y-4">
                                    <PartnerOrganizationsField
                                        initial={
                                            partners.length > 0
                                                ? partners
                                                : null
                                        }
                                        errors={errors}
                                        onChange={setPartners}
                                    />
                                    <div className="space-y-1">
                                        <Label>
                                            Target SDG (select one or more)
                                        </Label>
                                        <SdgCheckboxGroup
                                            idPrefix="target-sdg"
                                            name="target_sdg[]"
                                            options={sdgs}
                                            selected={targetSdg}
                                            onChange={setTargetSdg}
                                        />
                                        <InputError
                                            message={errors.target_sdg}
                                        />
                                    </div>
                                </div>
                            </FixItemBox>
                        </FixSection>

                        <FixSection title="Budget" itemKeys={['budget']}>
                            <FixItemBox
                                sectionKey="budget"
                                changed={changed('budget')}
                                locked={
                                    <LockedGrid>
                                        <LockedField
                                            label="Proposed budget"
                                            value={
                                                base.budget.proposed_budget &&
                                                `₱${money(Number(base.budget.proposed_budget))}`
                                            }
                                        />
                                        <LockedField
                                            label="Budget source"
                                            value={label(
                                                budgetSources,
                                                base.budget.budget_source,
                                            )}
                                        />
                                        <LockedField
                                            label="Expenses"
                                            value={
                                                base.budget.expense_items
                                                    .length > 0
                                                    ? base.budget.expense_items
                                                          .map(
                                                              (item) =>
                                                                  `${item.material} (${item.quantity} × ₱${money(Number(item.unit_price) || 0)})`,
                                                          )
                                                          .join('; ')
                                                    : null
                                            }
                                        />
                                    </LockedGrid>
                                }
                            >
                                <div className="space-y-4">
                                    <div className="space-y-1">
                                        <div className="flex items-center justify-between">
                                            <Label>Expenses</Label>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setExpenseItems((prev) => [
                                                        ...prev,
                                                        BLANK_EXPENSE,
                                                    ])
                                                }
                                            >
                                                + Add Item
                                            </Button>
                                        </div>
                                        {proposal?.expenses && (
                                            <p className="rounded-md border border-dashed bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                                                Previously entered as text —
                                                re-enter it below as itemized
                                                rows: “{proposal.expenses}”
                                            </p>
                                        )}
                                        {expenseItems.map((item, i) => (
                                            <div key={i} className="space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Input
                                                        name={`expense_items[${i}][material]`}
                                                        aria-label={`Expense ${i + 1} material`}
                                                        value={item.material}
                                                        onChange={(e) =>
                                                            setExpenseItems(
                                                                (prev) =>
                                                                    prev.map(
                                                                        (
                                                                            row,
                                                                            idx,
                                                                        ) =>
                                                                            idx ===
                                                                            i
                                                                                ? {
                                                                                      ...row,
                                                                                      material:
                                                                                          e
                                                                                              .target
                                                                                              .value,
                                                                                  }
                                                                                : row,
                                                                    ),
                                                            )
                                                        }
                                                        placeholder="Material (e.g. Tarpaulin)"
                                                        className="min-w-40 flex-1"
                                                    />
                                                    <Input
                                                        name={`expense_items[${i}][quantity]`}
                                                        aria-label={`Expense ${i + 1} quantity`}
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        value={item.quantity}
                                                        onChange={(e) =>
                                                            setExpenseItems(
                                                                (prev) =>
                                                                    prev.map(
                                                                        (
                                                                            row,
                                                                            idx,
                                                                        ) =>
                                                                            idx ===
                                                                            i
                                                                                ? {
                                                                                      ...row,
                                                                                      quantity:
                                                                                          e
                                                                                              .target
                                                                                              .value,
                                                                                  }
                                                                                : row,
                                                                    ),
                                                            )
                                                        }
                                                        placeholder="Qty"
                                                        className="w-20"
                                                    />
                                                    <Input
                                                        name={`expense_items[${i}][unit_price]`}
                                                        aria-label={`Expense ${i + 1} unit price`}
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        value={item.unit_price}
                                                        onChange={(e) =>
                                                            setExpenseItems(
                                                                (prev) =>
                                                                    prev.map(
                                                                        (
                                                                            row,
                                                                            idx,
                                                                        ) =>
                                                                            idx ===
                                                                            i
                                                                                ? {
                                                                                      ...row,
                                                                                      unit_price:
                                                                                          e
                                                                                              .target
                                                                                              .value,
                                                                                  }
                                                                                : row,
                                                                    ),
                                                            )
                                                        }
                                                        placeholder="Unit price"
                                                        className="w-28"
                                                    />
                                                    {expenseItems.length >
                                                        1 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setExpenseItems(
                                                                    (prev) =>
                                                                        prev.filter(
                                                                            (
                                                                                _,
                                                                                idx,
                                                                            ) =>
                                                                                idx !==
                                                                                i,
                                                                        ),
                                                                )
                                                            }
                                                        >
                                                            Remove
                                                        </Button>
                                                    )}
                                                </div>
                                                <div className="flex justify-end text-xs text-muted-foreground">
                                                    Line total: ₱
                                                    {money(rowTotal(item))}
                                                </div>
                                                <InputError
                                                    message={
                                                        errors[
                                                            `expense_items.${i}.material`
                                                        ] ??
                                                        errors[
                                                            `expense_items.${i}.quantity`
                                                        ] ??
                                                        errors[
                                                            `expense_items.${i}.unit_price`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        ))}
                                        <div className="flex items-center justify-end gap-2 border-t pt-2 text-sm">
                                            <span className="font-medium text-muted-foreground">
                                                Total
                                            </span>
                                            <span className="font-semibold tabular-nums">
                                                ₱{money(expenseTotal)}
                                            </span>
                                        </div>
                                        <InputError
                                            message={errors.expense_items}
                                        />
                                    </div>

                                    <div className="space-y-1">
                                        <Label htmlFor="proposed_budget">
                                            Proposed Budget
                                        </Label>
                                        <Input
                                            id="proposed_budget"
                                            name="proposed_budget"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={proposedBudget}
                                            onChange={(e) =>
                                                setProposedBudget(
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.proposed_budget}
                                        />
                                    </div>

                                    <div className="space-y-1">
                                        <Label htmlFor="budget_source">
                                            Budget Source
                                        </Label>
                                        <Select
                                            name="budget_source"
                                            value={budgetSource || undefined}
                                            onValueChange={setBudgetSource}
                                        >
                                            <SelectTrigger id="budget_source">
                                                <SelectValue placeholder="Select source…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {budgetSources.map((b) => (
                                                    <SelectItem
                                                        key={b.value}
                                                        value={b.value}
                                                    >
                                                        {b.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.budget_source}
                                        />
                                    </div>
                                </div>
                            </FixItemBox>
                        </FixSection>

                        <FixSection
                            title="Schedule and venue"
                            itemKeys={['schedule_venue']}
                        >
                            {isOffCalendar || fix.active ? (
                                <FixItemBox
                                    sectionKey="schedule_venue"
                                    changed={changed('schedule_venue')}
                                    locked={
                                        <LockedGrid>
                                            <LockedField
                                                label="Venue"
                                                value={
                                                    base.schedule_venue.venue
                                                }
                                            />
                                            <LockedField
                                                label="Date"
                                                value={formatLongDate(
                                                    base.schedule_venue
                                                        .activity_date,
                                                )}
                                            />
                                            <LockedField
                                                label="Time"
                                                value={`${base.schedule_venue.start_time} – ${base.schedule_venue.end_time}`}
                                            />
                                        </LockedGrid>
                                    }
                                >
                                    <div className="space-y-4">
                                        <div className="space-y-1">
                                            <Label htmlFor="venue">Venue</Label>
                                            <Input
                                                id="venue"
                                                name="venue"
                                                value={schedule.venue}
                                                onChange={(e) =>
                                                    setSchedule((prev) => ({
                                                        ...prev,
                                                        venue: e.target.value,
                                                    }))
                                                }
                                            />
                                            <InputError
                                                message={errors.venue}
                                            />
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-3">
                                            <div className="space-y-1">
                                                <Label htmlFor="activity_date">
                                                    Date of Activity
                                                </Label>
                                                <Input
                                                    id="activity_date"
                                                    name="activity_date"
                                                    type="date"
                                                    value={
                                                        schedule.activity_date
                                                    }
                                                    min={minDate}
                                                    onChange={(e) =>
                                                        setSchedule((prev) => ({
                                                            ...prev,
                                                            activity_date:
                                                                e.target.value,
                                                        }))
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.activity_date
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label htmlFor="start_time">
                                                    Start
                                                </Label>
                                                <Input
                                                    id="start_time"
                                                    name="start_time"
                                                    type="time"
                                                    value={schedule.start_time}
                                                    onChange={(e) =>
                                                        setSchedule((prev) => ({
                                                            ...prev,
                                                            start_time:
                                                                e.target.value,
                                                        }))
                                                    }
                                                />
                                                <InputError
                                                    message={errors.start_time}
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label htmlFor="end_time">
                                                    End
                                                </Label>
                                                <Input
                                                    id="end_time"
                                                    name="end_time"
                                                    type="time"
                                                    value={schedule.end_time}
                                                    onChange={(e) =>
                                                        setSchedule((prev) => ({
                                                            ...prev,
                                                            end_time:
                                                                e.target.value,
                                                        }))
                                                    }
                                                />
                                                <InputError
                                                    message={errors.end_time}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </FixItemBox>
                            ) : (
                                activity && (
                                    <div className="space-y-2">
                                        <p className="text-sm leading-snug font-medium">
                                            Calendar activity
                                        </p>
                                        <ActivityCard
                                            activity={{
                                                id: String(activity.id ?? ''),
                                                title: activity.name,
                                                date: activity.activity_date,
                                                start_time: activity.start_time,
                                                end_time: activity.end_time,
                                                venue: activity.venue,
                                            }}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            The date and venue come from the
                                            approved calendar.
                                        </p>
                                    </div>
                                )
                            )}
                            <InputError message={errors.activity} />
                        </FixSection>

                        {/* ── Step 2: the narrative ──────────────────────────────── */}
                        <FixSection
                            title="Proposal narrative"
                            itemKeys={[
                                'objectives',
                                'activity_description',
                                'responsible_persons',
                            ]}
                        >
                            <FixItemBox
                                sectionKey="objectives"
                                changed={changed('objectives')}
                                locked={
                                    <LockedGrid columns={1}>
                                        <LockedField
                                            label="Objectives"
                                            value={base.objectives.objectives}
                                        />
                                    </LockedGrid>
                                }
                            >
                                <div className="space-y-1">
                                    <Label htmlFor="objectives">
                                        Objectives
                                    </Label>
                                    <Textarea
                                        id="objectives"
                                        name="objectives"
                                        value={objectives}
                                        onChange={(e) =>
                                            setObjectives(e.target.value)
                                        }
                                        placeholder={
                                            'Describe the overall goal of the activity.\nList specific measurable objectives.'
                                        }
                                        rows={6}
                                    />
                                    <InputError message={errors.objectives} />
                                </div>
                            </FixItemBox>

                            <FixItemBox
                                sectionKey="activity_description"
                                changed={changed('activity_description')}
                                locked={
                                    <LockedGrid columns={1}>
                                        <LockedField
                                            label="Activity description"
                                            value={
                                                base.activity_description
                                                    .activity_description
                                            }
                                        />
                                        <LockedField
                                            label="Criteria/Mechanics"
                                            value={
                                                base.activity_description
                                                    .criteria_mechanics
                                            }
                                        />
                                        <LockedField
                                            label="Program flow"
                                            value={
                                                base.activity_description
                                                    .program_flow
                                            }
                                        />
                                    </LockedGrid>
                                }
                            >
                                <div className="space-y-4">
                                    <div className="space-y-1">
                                        <Label htmlFor="activity_description">
                                            Activity Description
                                        </Label>
                                        <Textarea
                                            id="activity_description"
                                            name="activity_description"
                                            value={
                                                description.activity_description
                                            }
                                            onChange={(e) =>
                                                setDescription((prev) => ({
                                                    ...prev,
                                                    activity_description:
                                                        e.target.value,
                                                }))
                                            }
                                            rows={6}
                                        />
                                        <InputError
                                            message={
                                                errors.activity_description
                                            }
                                        />
                                    </div>
                                    {/* Criteria/Mechanics and Program Flow are detail under
                                        Activity Description, not equal siblings. */}
                                    <div className="space-y-3 border-l-2 pl-4">
                                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Details
                                        </p>
                                        <div className="space-y-1">
                                            <Label
                                                htmlFor="criteria_mechanics"
                                                className="text-sm font-normal text-muted-foreground"
                                            >
                                                Criteria/Mechanics
                                            </Label>
                                            <Textarea
                                                id="criteria_mechanics"
                                                name="criteria_mechanics"
                                                value={
                                                    description.criteria_mechanics
                                                }
                                                onChange={(e) =>
                                                    setDescription((prev) => ({
                                                        ...prev,
                                                        criteria_mechanics:
                                                            e.target.value,
                                                    }))
                                                }
                                                rows={3}
                                            />
                                            <InputError
                                                message={
                                                    errors.criteria_mechanics
                                                }
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label
                                                htmlFor="program_flow"
                                                className="text-sm font-normal text-muted-foreground"
                                            >
                                                Program Flow
                                            </Label>
                                            <Textarea
                                                id="program_flow"
                                                name="program_flow"
                                                value={description.program_flow}
                                                onChange={(e) =>
                                                    setDescription((prev) => ({
                                                        ...prev,
                                                        program_flow:
                                                            e.target.value,
                                                    }))
                                                }
                                                rows={3}
                                            />
                                            <InputError
                                                message={errors.program_flow}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </FixItemBox>

                            <FixItemBox
                                sectionKey="responsible_persons"
                                changed={changed('responsible_persons')}
                                locked={
                                    <LockedGrid columns={1}>
                                        <LockedField
                                            label="Responsible person(s)"
                                            value={base.responsible_persons.responsible_persons.join(
                                                ', ',
                                            )}
                                        />
                                    </LockedGrid>
                                }
                            >
                                <div className="space-y-1">
                                    <div className="flex items-center justify-between">
                                        <Label>Responsible Person(s)</Label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setResponsible((prev) => [
                                                    ...prev,
                                                    '',
                                                ])
                                            }
                                        >
                                            + Add
                                        </Button>
                                    </div>
                                    {responsible.map((name, i) => (
                                        <div key={i} className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <Input
                                                    name={`responsible_persons[${i}]`}
                                                    aria-label={`Responsible person ${i + 1}`}
                                                    value={name}
                                                    onChange={(e) =>
                                                        setResponsible((prev) =>
                                                            prev.map(
                                                                (
                                                                    current,
                                                                    idx,
                                                                ) =>
                                                                    idx === i
                                                                        ? e
                                                                              .target
                                                                              .value
                                                                        : current,
                                                            ),
                                                        )
                                                    }
                                                    placeholder="Full name"
                                                />
                                                {responsible.length > 1 && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setResponsible(
                                                                (prev) =>
                                                                    prev.filter(
                                                                        (
                                                                            _,
                                                                            idx,
                                                                        ) =>
                                                                            idx !==
                                                                            i,
                                                                    ),
                                                            )
                                                        }
                                                    >
                                                        Remove
                                                    </Button>
                                                )}
                                            </div>
                                            <InputError
                                                message={
                                                    errors[
                                                        `responsible_persons.${i}`
                                                    ]
                                                }
                                            />
                                        </div>
                                    ))}
                                    <InputError
                                        message={errors.responsible_persons}
                                    />
                                </div>
                            </FixItemBox>
                        </FixSection>

                        {/* Step-1 attachments are bundled into this same PUT. */}
                        {fix.active ? (
                            <FixSection
                                title="Attachments"
                                itemKeys={flaggedSlots.map((slot) => slot.key)}
                                lockedLabel={lockedSummary(
                                    attachmentSlots,
                                    flaggedKeys,
                                )}
                            >
                                {flaggedSlots.map((slot) => (
                                    <FixItemBox
                                        key={slot.key}
                                        sectionKey={slot.key}
                                        changed={
                                            (files[slot.key]?.length ?? 0) > 0
                                        }
                                        badges={fileBadges(
                                            (files[slot.key]?.length ?? 0) > 0,
                                        )}
                                        locked={null}
                                    >
                                        <FlaggedFile
                                            slot={slot}
                                            existing={
                                                attachments[slot.key] ?? []
                                            }
                                            files={files[slot.key] ?? []}
                                            onFilesChange={(list) =>
                                                setFiles((prev) => ({
                                                    ...prev,
                                                    [slot.key]: list,
                                                }))
                                            }
                                            error={
                                                errors[
                                                    `attachments.${slot.key}`
                                                ]
                                            }
                                        />
                                    </FixItemBox>
                                ))}
                                <LockedRequirements
                                    slots={lockedSlots}
                                    existing={attachments}
                                />
                            </FixSection>
                        ) : (
                            <FixSection title="Attachments" itemKeys={[]}>
                                {attachmentSlots.map((slot) => (
                                    <AttachmentSlotField
                                        key={slot.key}
                                        slot={slot}
                                        existing={attachments[slot.key]}
                                        error={
                                            errors[`attachments.${slot.key}`]
                                        }
                                    />
                                ))}
                            </FixSection>
                        )}

                        {fix.active ? (
                            <FixFooter
                                formId={formId}
                                processing={processing}
                                collect={() => {
                                    const values: Record<
                                        string,
                                        Record<string, unknown>
                                    > = {
                                        rso_info: current.rso_info,
                                        activity_details:
                                            current.activity_details,
                                        partner_orgs_sdg: {
                                            partner_organizations: partners,
                                            target_sdg: targetSdg,
                                        },
                                        budget: {
                                            proposed_budget: proposedBudget,
                                            budget_source: budgetSource,
                                            expense_items: expenseItems,
                                        },
                                        schedule_venue: current.schedule_venue,
                                        objectives: current.objectives,
                                        activity_description:
                                            current.activity_description,
                                        responsible_persons: {
                                            responsible_persons: responsible,
                                        },
                                    };

                                    return Object.fromEntries(
                                        Object.entries(values).filter(([key]) =>
                                            isFlagged(key),
                                        ),
                                    );
                                }}
                                title="Resend this proposal?"
                                description={`It goes back to ${fix.role}, who asked for these changes.`}
                            />
                        ) : (
                            <FormFooter
                                status={`${fix.role} sees it again as soon as you resend.`}
                            >
                                <FormSubmitConfirm
                                    formId={formId}
                                    processing={processing}
                                    title="Resend this proposal?"
                                    description={`It goes back to ${fix.role}, who returned it.`}
                                    confirmLabel={fix.resendLabel}
                                >
                                    {fix.resendLabel}
                                </FormSubmitConfirm>
                            </FormFooter>
                        )}
                    </FormCard>
                )}
            </Form>
        </FixShell>
    );
}

EditActivityProposal.layout = {
    breadcrumbs: [{ title: 'Activity Proposals' }, { title: 'Fix and Resend' }],
    columnWidth: '3xl',
};
