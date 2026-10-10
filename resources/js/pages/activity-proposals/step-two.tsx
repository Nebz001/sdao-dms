import { Form, Head, usePage } from '@inertiajs/react';
import { Calendar, Clock, MapPin, Plus, Send, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ExpenseItemsEditor, { EMPTY_EXPENSE_ITEM } from '@/components/expense-items-editor';
import type { ExpenseItem } from '@/components/expense-items-editor';
import {
    FocusFirstError,
    FormCard,
    FormField,
    FormFooter,
    FormSection,
    FormShell,
    FormStrip,
} from '@/components/form-shell';
import FormSubmitConfirm from '@/components/form-submit-confirm';
import type { PartnerOrganization } from '@/components/partner-organizations-field';
import { RelativeTime } from '@/components/relative-time';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatActivityDate, formatClockRange } from '@/lib/activity-time';
import { formatPeso, parseAmount } from '@/lib/money';
import { notify } from '@/lib/toast';
import * as activityProposals from '@/routes/activity-proposals';

type ActivitySummary = {
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
} | null;

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
    budget_source_label: string | null;
    // Group D item 5 — step 1 → step 2 carryover.
    activity_nature_label: string | null;
    activity_type_label: string | null;
    partner_organizations: PartnerOrganization[] | null;
    target_sdg_labels: string[];
} | null;

type DocumentData = {
    id: number;
    title: string;
};

type Props = {
    document: DocumentData;
    proposal: ProposalData;
    activity: ActivitySummary;
};

const STEPS = ['Request form', 'Narrative'];

/** A small label over its value; an empty value reads "None", never a blank. */
function SummaryItem({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium break-words">{value && value.trim() !== '' ? value : 'None'}</dd>
        </div>
    );
}

function Chip({ icon: Icon, children }: { icon: typeof MapPin; children: string }) {
    return (
        <li className="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs">
            <Icon aria-hidden className="size-3.5 text-muted-foreground" />
            {children}
        </li>
    );
}

export default function StepTwo({ document: doc, proposal, activity }: Props) {
    const { auth } = usePage().props;
    const formId = `narrative-form-${doc.id}`;

    const objectivesRef = useRef<HTMLTextAreaElement>(null);
    const activityDescriptionRef = useRef<HTMLTextAreaElement>(null);
    const criteriaMechanicsRef = useRef<HTMLTextAreaElement>(null);
    const programFlowRef = useRef<HTMLTextAreaElement>(null);
    const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [saveState, setSaveState] = useState<'idle' | 'saving' | 'saved' | 'failed'>('idle');
    const [savedAt, setSavedAt] = useState<string | null>(null);

    // Itemized expenses — a dynamic row list can't live behind a single ref
    // like the plain-text fields above, so it's state instead. A mirroring
    // ref keeps the debounced save reading the latest rows rather than a
    // stale closure over the state at the time the save was scheduled.
    const [expenseItems, setExpenseItems] = useState<ExpenseItem[]>(
        proposal?.expense_items && proposal.expense_items.length > 0
            ? proposal.expense_items
            : [{ ...EMPTY_EXPENSE_ITEM }],
    );
    const expenseItemsRef = useRef(expenseItems);
    useEffect(() => {
        expenseItemsRef.current = expenseItems;
    }, [expenseItems]);

    // Typed in directly by the submitting officer — not a picker sourced
    // from org membership (see ActivityProposal's responsible_persons
    // docblock). Same dynamic-row-list shape as expenseItems above.
    const [responsiblePersons, setResponsiblePersons] = useState<string[]>(
        proposal?.responsible_persons && proposal.responsible_persons.length > 0
            ? proposal.responsible_persons
            : [''],
    );
    const responsiblePersonsRef = useRef(responsiblePersons);
    useEffect(() => {
        responsiblePersonsRef.current = responsiblePersons;
    }, [responsiblePersons]);

    function xsrfToken(): string {
        return decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
    }

    /**
     * Plain fetch, not router.patch — this is a debounced, idempotent
     * background ping (see the controller's own doc comment: "never enters
     * chain"), not a page visit. The endpoint deliberately returns raw JSON,
     * not an Inertia response; routing it through Inertia's router
     * previously made its client reject that response and flash its
     * built-in "invalid response" error dialog. Same pattern as
     * ImmediateAttachmentUpload's uploads.
     */
    function persist(): Promise<boolean> {
        setSaveState('saving');

        return fetch(activityProposals.draft({ document: doc.id }).url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({
                objectives: objectivesRef.current?.value ?? null,
                activity_description: activityDescriptionRef.current?.value ?? null,
                criteria_mechanics: criteriaMechanicsRef.current?.value ?? null,
                program_flow: programFlowRef.current?.value ?? null,
                expense_items: expenseItemsRef.current,
                responsible_persons: responsiblePersonsRef.current,
            }),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('save failed');
                }

                setSavedAt(new Date().toISOString());
                setSaveState('saved');

                return true;
            })
            .catch(() => {
                // Best-effort autosave — a failed ping is retried on the
                // next keystroke or covered by the final validated
                // "Submit for Review" action.
                setSaveState('failed');

                return false;
            });
    }

    function scheduleSave() {
        if (saveTimer.current) {
            clearTimeout(saveTimer.current);
        }

        saveTimer.current = setTimeout(() => void persist(), 1500);
    }

    async function saveNow() {
        if (saveTimer.current) {
            clearTimeout(saveTimer.current);
        }

        if (await persist()) {
            notify.success({ title: 'Draft saved', message: 'Your narrative is saved. You can come back to it any time.' });
        } else {
            notify.error({ title: 'Could not save the draft', message: 'Check your connection and try again.' });
        }
    }

    const budget = proposal?.proposed_budget ? parseAmount(proposal.proposed_budget) : null;
    const time = activity ? formatClockRange(activity.start_time, activity.end_time) : null;

    return (
        <>
            <Head title={`Narrative — ${doc.title}`} />

            <FormShell
                title="New Activity Proposal"
                subtitle="Write the narrative. Once you submit, it goes to your adviser."
                steps={STEPS}
                currentStep={2}
            >
                <Form id={formId} action={activityProposals.submit({ document: doc.id }).url} method="post">
                    {({ processing, errors }) => (
                        <FormCard>
                            <FocusFirstError errors={errors} />
                            <FormStrip
                                left={
                                    <>
                                        Filing for{' '}
                                        <strong className="font-semibold text-foreground">
                                            {auth.organization?.name}
                                        </strong>
                                    </>
                                }
                                right={auth.organization?.school?.name}
                            />

                            {/* Set once at step 1, read-only here (Phase 2 item 7
                                slice 4a). Nature, Type, Partners and SDGs carry
                                over so the student sees what they picked while
                                writing step 2. */}
                            {activity && (
                                <FormSection title="Your activity">
                                    <div className="space-y-3">
                                        <h3 className="text-lg font-bold">{activity.name}</h3>
                                        <ul className="flex flex-wrap gap-2" aria-label="When and where">
                                            <Chip icon={MapPin}>{activity.venue}</Chip>
                                            <Chip icon={Calendar}>{formatActivityDate(activity.activity_date)}</Chip>
                                            {time && <Chip icon={Clock}>{time}</Chip>}
                                        </ul>
                                        <dl className="grid gap-x-6 gap-y-3 rounded-lg border bg-muted/30 p-4 sm:grid-cols-2">
                                            <SummaryItem label="Nature" value={proposal?.activity_nature_label} />
                                            <SummaryItem label="Type" value={proposal?.activity_type_label} />
                                            <SummaryItem
                                                label="Partner orgs"
                                                value={proposal?.partner_organizations?.map((o) => o.name).join(', ')}
                                            />
                                            <SummaryItem
                                                label="Target SDGs"
                                                value={proposal?.target_sdg_labels.join(', ')}
                                            />
                                            <SummaryItem
                                                label="Proposed budget"
                                                value={budget !== null ? formatPeso(budget) : null}
                                            />
                                            <SummaryItem label="Budget source" value={proposal?.budget_source_label} />
                                        </dl>
                                    </div>
                                </FormSection>
                            )}

                            <FormSection title="Objectives">
                                <FormField id="objectives" label="Objectives" error={errors.objectives}>
                                    {(aria) => (
                                        <Textarea
                                            id="objectives"
                                            name="objectives"
                                            ref={objectivesRef}
                                            defaultValue={proposal?.objectives ?? ''}
                                            placeholder={'Describe the overall goal of the activity.\nList specific measurable objectives.'}
                                            rows={5}
                                            onChange={scheduleSave}
                                            {...aria}
                                        />
                                    )}
                                </FormField>
                            </FormSection>

                            <FormSection title="Activity description">
                                <FormField id="activity_description" label="Description" error={errors.activity_description}>
                                    {(aria) => (
                                        <Textarea
                                            id="activity_description"
                                            name="activity_description"
                                            ref={activityDescriptionRef}
                                            defaultValue={proposal?.activity_description ?? ''}
                                            placeholder="What will happen during the activity?"
                                            rows={5}
                                            onChange={scheduleSave}
                                            {...aria}
                                        />
                                    )}
                                </FormField>
                                <FormField id="criteria_mechanics" label="Criteria/Mechanics" error={errors.criteria_mechanics}>
                                    {(aria) => (
                                        <Textarea
                                            id="criteria_mechanics"
                                            name="criteria_mechanics"
                                            ref={criteriaMechanicsRef}
                                            defaultValue={proposal?.criteria_mechanics ?? ''}
                                            placeholder="Rules, criteria for judging, or how it works"
                                            rows={4}
                                            onChange={scheduleSave}
                                            {...aria}
                                        />
                                    )}
                                </FormField>
                                <FormField id="program_flow" label="Program Flow" error={errors.program_flow}>
                                    {(aria) => (
                                        <Textarea
                                            id="program_flow"
                                            name="program_flow"
                                            ref={programFlowRef}
                                            defaultValue={proposal?.program_flow ?? ''}
                                            placeholder="Order of activities, from opening to closing"
                                            rows={4}
                                            onChange={scheduleSave}
                                            {...aria}
                                        />
                                    )}
                                </FormField>
                            </FormSection>

                            <FormSection title="Expenses" aside="List every item you’ll spend on">
                                {proposal?.expenses && (
                                    <p className="rounded-md border border-dashed bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                                        Previously entered as text — re-enter it below as itemized rows: “{proposal.expenses}”
                                    </p>
                                )}
                                <ExpenseItemsEditor
                                    items={expenseItems}
                                    onChange={(next) => {
                                        setExpenseItems(next);
                                        scheduleSave();
                                    }}
                                    errors={errors}
                                    budget={budget}
                                />
                            </FormSection>

                            <FormSection title="Responsible persons">
                                <fieldset className="grid gap-2">
                                    <legend className="sr-only">Responsible persons</legend>
                                    {responsiblePersons.map((name, i) => {
                                        const error =
                                            errors[`responsible_persons.${i}`] ??
                                            (i === 0 ? errors.responsible_persons : undefined);

                                        return (
                                            <div key={i} className="grid gap-1">
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        name={`responsible_persons[${i}]`}
                                                        value={name}
                                                        onChange={(e) => {
                                                            setResponsiblePersons((prev) =>
                                                                prev.map((current, idx) => (idx === i ? e.target.value : current)),
                                                            );
                                                            scheduleSave();
                                                        }}
                                                        placeholder="Full name"
                                                        aria-label={`Responsible person ${i + 1}`}
                                                        aria-invalid={error ? true : undefined}
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        disabled={responsiblePersons.length === 1}
                                                        aria-label={`Remove responsible person ${i + 1}`}
                                                        onClick={() => {
                                                            setResponsiblePersons((prev) => prev.filter((_, idx) => idx !== i));
                                                            scheduleSave();
                                                        }}
                                                    >
                                                        <X aria-hidden />
                                                    </Button>
                                                </div>
                                                {error && <p className="text-sm text-destructive">{error}</p>}
                                            </div>
                                        );
                                    })}
                                    <Button
                                        type="button"
                                        variant="link"
                                        size="sm"
                                        className="h-auto w-fit p-0"
                                        onClick={() => {
                                            setResponsiblePersons((prev) => [...prev, '']);
                                            scheduleSave();
                                        }}
                                    >
                                        <Plus aria-hidden />
                                        Add another person
                                    </Button>
                                </fieldset>
                                {errors.activity && <p className="text-sm text-destructive">{errors.activity}</p>}
                            </FormSection>

                            {/* Group C item 3 — all attachment slots moved to
                                step 1; step 2 no longer collects any. */}

                            <FormFooter
                                status={
                                    saveState === 'saving' ? (
                                        'Saving…'
                                    ) : saveState === 'saved' && savedAt ? (
                                        <>
                                            Saved as draft <RelativeTime dateString={savedAt} />
                                        </>
                                    ) : saveState === 'failed' ? (
                                        'Couldn’t save just now. We’ll try again as you type.'
                                    ) : (
                                        'Your narrative saves as you type.'
                                    )
                                }
                            >
                                <Button type="button" variant="outline" onClick={() => void saveNow()} disabled={saveState === 'saving'}>
                                    Save draft
                                </Button>
                                <FormSubmitConfirm
                                    formId={formId}
                                    processing={processing}
                                    title="Submit this proposal for review?"
                                    description="It goes to your adviser first. You can’t edit it unless an approver returns it."
                                    confirmLabel="Submit for Review"
                                >
                                    Submit for Review
                                    <Send aria-hidden />
                                </FormSubmitConfirm>
                            </FormFooter>
                        </FormCard>
                    )}
                </Form>
            </FormShell>
        </>
    );
}

StepTwo.layout = {
    breadcrumbs: [
        { title: 'Activity Proposals', href: '/activity-proposals' },
        { title: 'Narrative' },
    ],
    columnWidth: '3xl',
};
