import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import AfterActivityReportController from '@/actions/App/Http/Controllers/AfterActivityReportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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

type Membership = {
    id: number;
    position: string;
    position_label: string;
    organization: { id: number; name: string };
};

type EligibleProposal = {
    activity_proposal_id: number;
    title: string;
    activity: {
        name: string;
        venue: string;
        activity_date: string;
    } | null;
};

type Props = {
    membership: Membership | null;
    eligibleProposals: EligibleProposal[];
};

export default function CreateReport({ membership, eligibleProposals }: Props) {
    const [chairs, setChairs] = useState<string[]>(['']);

    if (!membership) {
        return (
            <>
                <Head title="Submit After-Activity Report" />
                <div className="mx-auto max-w-2xl p-8">
                    <p className="text-sm text-muted-foreground">
                        You are not bound as an officer of any organization. Contact your
                        adviser to be bound before submitting a report.
                    </p>
                </div>
            </>
        );
    }

    if (eligibleProposals.length === 0) {
        return (
            <>
                <Head title="Submit After-Activity Report" />
                <div className="mx-auto max-w-2xl p-8">
                    <p className="text-sm text-muted-foreground">
                        {membership.organization.name} has no approved activities awaiting a
                        report. A report can only be filed against an approved activity
                        proposal that does not already have one on file.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Submit After-Activity Report" />

            <div className="mx-auto max-w-2xl space-y-6 p-8">
                <Heading
                    title="After-Activity Report"
                    description={`Reporting for ${membership.organization.name} as ${membership.position_label}`}
                />

                <Form {...AfterActivityReportController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            {/* Activity proposal */}
                            <div className="grid gap-2">
                                <Label htmlFor="activity_proposal_id">Activity</Label>
                                <Select name="activity_proposal_id" required>
                                    <SelectTrigger id="activity_proposal_id" className="w-full">
                                        <SelectValue placeholder="Select the approved activity…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {eligibleProposals.map((p) => (
                                            <SelectItem
                                                key={p.activity_proposal_id}
                                                value={String(p.activity_proposal_id)}
                                            >
                                                {p.activity?.name ?? p.title}
                                                {p.activity && ` · ${p.activity.activity_date}`}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.activity_proposal_id} />
                            </div>

                            {/* Summary */}
                            <div className="grid gap-2">
                                <Label htmlFor="summary">Summary</Label>
                                <Textarea
                                    id="summary"
                                    name="summary"
                                    placeholder="Summarize how the activity was conducted…"
                                    rows={5}
                                    required
                                />
                                <InputError message={errors.summary} />
                            </div>

                            {/* Activity Chair/s */}
                            <div className="grid gap-2">
                                <div className="flex items-center justify-between">
                                    <Label>Activity Chair/s</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setChairs((prev) => [...prev, ''])}
                                    >
                                        + Add Chair
                                    </Button>
                                </div>
                                {chairs.map((chair, i) => (
                                    <div key={i} className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <Input
                                                name={`activity_chairs[${i}]`}
                                                value={chair}
                                                onChange={(e) =>
                                                    setChairs((prev) => {
                                                        const next = [...prev];
                                                        next[i] = e.target.value;

                                                        return next;
                                                    })
                                                }
                                                placeholder="Full name"
                                                required
                                            />
                                            {chairs.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setChairs((prev) => prev.filter((_, idx) => idx !== i))}
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                        <InputError message={errors[`activity_chairs.${i}`]} />
                                    </div>
                                ))}
                                <InputError message={errors.activity_chairs} />
                            </div>

                            {/* Prepared By */}
                            <div className="grid gap-2">
                                <Label htmlFor="prepared_by">Prepared By</Label>
                                <Input id="prepared_by" name="prepared_by" placeholder="Full name" required />
                                <InputError message={errors.prepared_by} />
                            </div>

                            {/* Program */}
                            <div className="grid gap-2">
                                <Label htmlFor="event_program">Program</Label>
                                <Textarea
                                    id="event_program"
                                    name="event_program"
                                    placeholder="Order of activities / program flow for the event…"
                                    rows={4}
                                    required
                                />
                                <InputError message={errors.event_program} />
                            </div>

                            {/* % Target Participants */}
                            <div className="grid gap-2">
                                <Label htmlFor="target_participants_percentage">
                                    Activity Evaluation Report — % Target Participants
                                </Label>
                                <Input
                                    id="target_participants_percentage"
                                    type="number"
                                    name="target_participants_percentage"
                                    min={0}
                                    max={100}
                                    required
                                />
                                <InputError message={errors.target_participants_percentage} />
                            </div>

                            {/* Outcomes */}
                            <div className="grid gap-2">
                                <Label htmlFor="outcomes">Outcomes</Label>
                                <Textarea
                                    id="outcomes"
                                    name="outcomes"
                                    placeholder="What were the results or outcomes? (optional)"
                                    rows={4}
                                />
                                <InputError message={errors.outcomes} />
                            </div>

                            {/* Participant count */}
                            <div className="grid gap-2">
                                <Label htmlFor="participant_count">Participant Count</Label>
                                <Input
                                    id="participant_count"
                                    type="number"
                                    name="participant_count"
                                    min={0}
                                    placeholder="Optional"
                                />
                                <InputError message={errors.participant_count} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Submit for Review</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateReport.layout = {
    breadcrumbs: [{ title: 'Reports' }, { title: 'New Report' }],
};
