import { Form, Head, router } from '@inertiajs/react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
    narrative: string | null;
    criteria_mechanics: string | null;
    program_flow: string | null;
    source_of_funding: string | null;
    expenses: string | null;
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
    errors?: Record<string, string>;
};

export default function StepTwo({ document: doc, proposal, activity, errors = {} }: Props) {
    const objectivesRef = useRef<HTMLTextAreaElement>(null);
    const narrativeRef = useRef<HTMLTextAreaElement>(null);
    const criteriaMechanicsRef = useRef<HTMLTextAreaElement>(null);
    const programFlowRef = useRef<HTMLTextAreaElement>(null);
    const sourceOfFundingRef = useRef<HTMLTextAreaElement>(null);
    const expensesRef = useRef<HTMLTextAreaElement>(null);
    const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    function scheduleSave() {
        if (saveTimer.current) {
clearTimeout(saveTimer.current);
}

        saveTimer.current = setTimeout(() => {
            router.patch(
                activityProposals.draft({ document: doc.id }).url,
                {
                    objectives: objectivesRef.current?.value ?? null,
                    narrative: narrativeRef.current?.value ?? null,
                    criteria_mechanics: criteriaMechanicsRef.current?.value ?? null,
                    program_flow: programFlowRef.current?.value ?? null,
                    source_of_funding: sourceOfFundingRef.current?.value ?? null,
                    expenses: expensesRef.current?.value ?? null,
                },
                { preserveState: true, preserveScroll: true },
            );
        }, 1500);
    }

    return (
        <>
            <Head title={`Narrative — ${doc.title}`} />

            <div className="mx-auto max-w-xl space-y-6 p-8">
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
                            <Label htmlFor="expenses">Expenses</Label>
                            <Textarea
                                id="expenses"
                                name="expenses"
                                ref={expensesRef}
                                defaultValue={proposal?.expenses ?? ''}
                                rows={4}
                                onChange={scheduleSave}
                            />
                            <InputError message={errors.expenses} />
                        </div>

                        <InputError message={errors.activity} />

                        <Button type="submit" className="w-full">
                            Submit for Review
                        </Button>
                    </div>
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
