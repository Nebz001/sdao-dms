import { Form, Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import ImmediateAttachmentUpload from '@/components/immediate-attachment-upload';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import * as activityProposals from '@/routes/activity-proposals';

type ExpenseItem = { label: string; amount: string };

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
    narrative: string | null;
    criteria_mechanics: string | null;
    program_flow: string | null;
    source_of_funding: string | null;
    expenses: string | null;
    expense_items: ExpenseItem[] | null;
    proposed_budget: string | null;
    budget_source: string | null;
} | null;

type DocumentData = {
    id: number;
    title: string;
};

type Props = {
    document: DocumentData;
    proposal: ProposalData;
    activity: ActivitySummary;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
};

export default function StepTwo({ document: doc, proposal, activity, attachmentSlots, attachments }: Props) {
    const objectivesRef = useRef<HTMLTextAreaElement>(null);
    const narrativeRef = useRef<HTMLTextAreaElement>(null);
    const criteriaMechanicsRef = useRef<HTMLTextAreaElement>(null);
    const programFlowRef = useRef<HTMLTextAreaElement>(null);
    const sourceOfFundingRef = useRef<HTMLTextAreaElement>(null);
    const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Itemized expenses — a dynamic row list can't live behind a single ref
    // like the plain-text fields above, so it's state instead. A mirroring
    // ref keeps scheduleSave()'s debounced setTimeout callback reading the
    // latest rows rather than a stale closure over the state at the time
    // scheduleSave was called.
    const [expenseItems, setExpenseItems] = useState<ExpenseItem[]>(
        proposal?.expense_items && proposal.expense_items.length > 0 ? proposal.expense_items : [{ label: '', amount: '' }],
    );
    const expenseItemsRef = useRef(expenseItems);
    useEffect(() => {
        expenseItemsRef.current = expenseItems;
    }, [expenseItems]);

    const expenseTotal = expenseItems.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);

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
                    narrative: narrativeRef.current?.value ?? null,
                    criteria_mechanics: criteriaMechanicsRef.current?.value ?? null,
                    program_flow: programFlowRef.current?.value ?? null,
                    source_of_funding: sourceOfFundingRef.current?.value ?? null,
                    expense_items: expenseItemsRef.current,
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

                {/* Activity summary + Proposed Budget/Budget Source read-only echo
                    (Phase 2 item 7 slice 4a — set once at step 1, not editable here) */}
                {activity && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Activity</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p className="font-medium">{activity.name}</p>
                            <p className="text-muted-foreground">
                                {activity.venue} · {activity.activity_date} · {activity.start_time}–{activity.end_time}
                            </p>
                            {proposal?.proposed_budget && (
                                <p className="mt-2 text-muted-foreground">
                                    <span className="font-medium text-foreground">Proposed Budget:</span>{' '}
                                    {proposal.proposed_budget}
                                </p>
                            )}
                            {proposal?.budget_source && (
                                <p className="text-muted-foreground">
                                    <span className="font-medium text-foreground">Budget Source:</span> {proposal.budget_source}
                                </p>
                            )}
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
                                rows={4}
                                onChange={scheduleSave}
                            />
                            <InputError message={errors.objectives} />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="narrative">Narrative / Description</Label>
                            <Textarea
                                id="narrative"
                                name="narrative"
                                ref={narrativeRef}
                                defaultValue={proposal?.narrative ?? ''}
                                rows={6}
                                onChange={scheduleSave}
                            />
                            <InputError message={errors.narrative} />
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
                            <Label htmlFor="source_of_funding">Source of Funding</Label>
                            <Textarea
                                id="source_of_funding"
                                name="source_of_funding"
                                ref={sourceOfFundingRef}
                                defaultValue={proposal?.source_of_funding ?? ''}
                                rows={3}
                                onChange={scheduleSave}
                            />
                            <InputError message={errors.source_of_funding} />
                        </div>

                        <div className="space-y-1">
                            <div className="flex items-center justify-between">
                                <Label>Expenses</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setExpenseItems((prev) => [...prev, { label: '', amount: '' }]);
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
                                            name={`expense_items[${i}][label]`}
                                            value={item.label}
                                            onChange={(e) => {
                                                setExpenseItems((prev) => {
                                                    const next = [...prev];
                                                    next[i] = { ...next[i], label: e.target.value };

                                                    return next;
                                                });
                                                scheduleSave();
                                            }}
                                            placeholder="Item (e.g. Venue rental)"
                                            className="flex-1"
                                        />
                                        <Input
                                            name={`expense_items[${i}][amount]`}
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.amount}
                                            onChange={(e) => {
                                                setExpenseItems((prev) => {
                                                    const next = [...prev];
                                                    next[i] = { ...next[i], amount: e.target.value };

                                                    return next;
                                                });
                                                scheduleSave();
                                            }}
                                            placeholder="0.00"
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
                                    <InputError message={errors[`expense_items.${i}.label`] ?? errors[`expense_items.${i}.amount`]} />
                                </div>
                            ))}
                            <div className="flex items-center justify-end gap-2 border-t pt-2 text-sm">
                                <span className="font-medium text-muted-foreground">Total</span>
                                <span className="font-semibold tabular-nums">
                                    ₱{expenseTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </span>
                            </div>
                            <InputError message={errors.expense_items} />
                        </div>

                        {attachmentSlots.map((slot) => (
                            <ImmediateAttachmentUpload
                                key={slot.key}
                                documentId={doc.id}
                                slot={slot}
                                existing={attachments[slot.key]?.[0] ?? null}
                            />
                        ))}

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
