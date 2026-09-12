import { Form, Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Row } from '@/components/labeled-row';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import * as activityProposals from '@/routes/activity-proposals';

type ExpenseItem = { material: string; quantity: string; unit_price: string };

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
    partner_organizations: string[] | null;
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

function rowTotal(item: ExpenseItem): number {
    return (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
}

function money(amount: number): string {
    return amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function StepTwo({ document: doc, proposal, activity }: Props) {
    const objectivesRef = useRef<HTMLTextAreaElement>(null);
    const criteriaMechanicsRef = useRef<HTMLTextAreaElement>(null);
    const programFlowRef = useRef<HTMLTextAreaElement>(null);
    const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Itemized expenses — a dynamic row list can't live behind a single ref
    // like the plain-text fields above, so it's state instead. A mirroring
    // ref keeps scheduleSave()'s debounced setTimeout callback reading the
    // latest rows rather than a stale closure over the state at the time
    // scheduleSave was called.
    const [expenseItems, setExpenseItems] = useState<ExpenseItem[]>(
        proposal?.expense_items && proposal.expense_items.length > 0
            ? proposal.expense_items
            : [{ material: '', quantity: '', unit_price: '' }],
    );
    const expenseItemsRef = useRef(expenseItems);
    useEffect(() => {
        expenseItemsRef.current = expenseItems;
    }, [expenseItems]);

    const expenseTotal = expenseItems.reduce((sum, item) => sum + rowTotal(item), 0);

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

    function scheduleSave() {
        if (saveTimer.current) {
            clearTimeout(saveTimer.current);
        }

        saveTimer.current = setTimeout(() => {
            // Plain fetch, not router.patch — this is a debounced,
            // idempotent background ping (see the controller's own doc
            // comment: "never enters chain"), not a page visit. The
            // endpoint deliberately returns raw JSON, not an Inertia
            // response; routing it through Inertia's router previously
            // made its client reject that response and flash its built-in
            // "invalid response" error dialog. Same pattern as
            // ImmediateAttachmentUpload's uploads.
            fetch(activityProposals.draft({ document: doc.id }).url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    objectives: objectivesRef.current?.value ?? null,
                    criteria_mechanics: criteriaMechanicsRef.current?.value ?? null,
                    program_flow: programFlowRef.current?.value ?? null,
                    expense_items: expenseItemsRef.current,
                    responsible_persons: responsiblePersonsRef.current,
                }),
            }).catch(() => {
                // Best-effort autosave — a failed ping is silently retried
                // on the next keystroke or covered by the final validated
                // "Submit for Review" action.
            });
        }, 1500);
    }

    return (
        <>
            <Head title={`Narrative — ${doc.title}`} />

            <div className="max-w-xl space-y-6">
                <div>
                    <h1 className="text-xl font-semibold">Activity Proposal — Narrative</h1>
                    <p className="mt-1 text-sm text-muted-foreground">{doc.title}</p>
                </div>

                {/* Activity summary + step-1 read-only echoes (Phase 2 item 7
                    slice 4a — set once at step 1, not editable here). Group D
                    item 5 — Nature/Type/Partners/SDG carried over so the
                    student can see what they picked at step 1 while writing
                    step 2. */}
                {activity && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Activity</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <p className="font-medium">{activity.name}</p>
                                <p className="text-muted-foreground">
                                    {activity.venue} · {activity.activity_date} · {activity.start_time}–{activity.end_time}
                                </p>
                            </div>
                            <div className="grid gap-1.5">
                                {proposal?.activity_nature_label && (
                                    <Row label="Nature of Activity" value={proposal.activity_nature_label} />
                                )}
                                {proposal?.activity_type_label && (
                                    <Row label="Type of Activity" value={proposal.activity_type_label} />
                                )}
                                {proposal?.partner_organizations && proposal.partner_organizations.length > 0 && (
                                    <Row label="Partner Org(s)" value={proposal.partner_organizations.join(', ')} />
                                )}
                                {proposal && proposal.target_sdg_labels.length > 0 && (
                                    <Row label="Target SDG" value={proposal.target_sdg_labels.join(', ')} />
                                )}
                                {proposal?.proposed_budget && (
                                    <Row label="Proposed Budget" value={`₱${proposal.proposed_budget}`} />
                                )}
                                {proposal?.budget_source_label && (
                                    <Row label="Budget Source" value={proposal.budget_source_label} />
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Form action={activityProposals.submit({ document: doc.id }).url} method="post">
                    {({ processing, errors }) => (
                    <div className="space-y-4">
                        <div className="space-y-1">
                            <Label htmlFor="objectives">Objectives</Label>
                            <Textarea
                                id="objectives"
                                name="objectives"
                                ref={objectivesRef}
                                defaultValue={proposal?.objectives ?? ''}
                                placeholder={'Describe the overall goal of the activity.\nList specific measurable objectives.'}
                                rows={6}
                                onChange={scheduleSave}
                            />
                            <InputError message={errors.objectives} />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="criteria_mechanics">Criteria/Mechanics</Label>
                            <Textarea
                                id="criteria_mechanics"
                                name="criteria_mechanics"
                                ref={criteriaMechanicsRef}
                                defaultValue={proposal?.criteria_mechanics ?? ''}
                                rows={4}
                                onChange={scheduleSave}
                            />
                            <InputError message={errors.criteria_mechanics} />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="program_flow">Program Flow</Label>
                            <Textarea
                                id="program_flow"
                                name="program_flow"
                                ref={programFlowRef}
                                defaultValue={proposal?.program_flow ?? ''}
                                rows={4}
                                onChange={scheduleSave}
                            />
                            <InputError message={errors.program_flow} />
                        </div>

                        <div className="space-y-1">
                            <div className="flex items-center justify-between">
                                <Label>Expenses</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setExpenseItems((prev) => [...prev, { material: '', quantity: '', unit_price: '' }]);
                                        scheduleSave();
                                    }}
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
                                            onChange={(e) => {
                                                setExpenseItems((prev) => {
                                                    const next = [...prev];
                                                    next[i] = { ...next[i], material: e.target.value };

                                                    return next;
                                                });
                                                scheduleSave();
                                            }}
                                            placeholder="Material (e.g. Tarpaulin)"
                                            className="flex-1"
                                        />
                                        <Input
                                            name={`expense_items[${i}][quantity]`}
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.quantity}
                                            onChange={(e) => {
                                                setExpenseItems((prev) => {
                                                    const next = [...prev];
                                                    next[i] = { ...next[i], quantity: e.target.value };

                                                    return next;
                                                });
                                                scheduleSave();
                                            }}
                                            placeholder="Qty"
                                            className="w-20"
                                        />
                                        <Input
                                            name={`expense_items[${i}][unit_price]`}
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.unit_price}
                                            onChange={(e) => {
                                                setExpenseItems((prev) => {
                                                    const next = [...prev];
                                                    next[i] = { ...next[i], unit_price: e.target.value };

                                                    return next;
                                                });
                                                scheduleSave();
                                            }}
                                            placeholder="Unit price"
                                            className="w-28"
                                        />
                                        {expenseItems.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    setExpenseItems((prev) => prev.filter((_, idx) => idx !== i));
                                                    scheduleSave();
                                                }}
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
                            <div className="flex items-center justify-between">
                                <Label>Responsible Person(s)</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setResponsiblePersons((prev) => [...prev, '']);
                                        scheduleSave();
                                    }}
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
                                            onChange={(e) => {
                                                setResponsiblePersons((prev) => {
                                                    const next = [...prev];
                                                    next[i] = e.target.value;

                                                    return next;
                                                });
                                                scheduleSave();
                                            }}
                                            placeholder="Full name"
                                        />
                                        {responsiblePersons.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    setResponsiblePersons((prev) => prev.filter((_, idx) => idx !== i));
                                                    scheduleSave();
                                                }}
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

                        {/* Group C item 3 — all attachment slots moved to
                            step 1; step 2 no longer collects any. */}

                        <InputError message={errors.activity} />

                        <Button type="submit" loading={processing} loadingText="Submitting…" className="w-full">
                            Submit for Review
                        </Button>
                    </div>
                    )}
                </Form>
            </div>
        </>
    );
}

StepTwo.layout = {
    breadcrumbs: [
        { title: 'Activity Proposals', href: '/activity-proposals' },
        { title: 'Narrative' },
    ],
};
