import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import * as activityProposals from '@/routes/activity-proposals';

type ProposalData = {
    calendar_mode: string;
    title: string;
    objectives: string | null;
    narrative: string | null;
    estimated_budget: string | null;
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
    errors?: Record<string, string>;
};

export default function EditActivityProposal({ document: doc, proposal, activity, errors = {} }: Props) {
    const isOffCalendar = proposal?.calendar_mode === 'off_calendar';

    return (
        <>
            <Head title={`Edit — ${doc.title}`} />

            <div className="mx-auto max-w-xl space-y-6 p-8">
                <h1 className="text-xl font-semibold">Edit Proposal</h1>
                <p className="text-sm text-muted-foreground">{doc.title}</p>

                {activity && (
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
                )}

                <Form action={activityProposals.update({ document: doc.id }).url} method="put">
                    <div className="space-y-4">
                        {/* Off-calendar: allow editing activity details */}
                        {isOffCalendar && (
                            <>
                                <div className="space-y-1">
                                    <Label htmlFor="title">Activity Title</Label>
                                    <Input id="title" name="title" defaultValue={proposal?.title ?? ''} />
                                    <InputError message={errors.title} />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="venue">Venue</Label>
                                    <Input id="venue" name="venue" defaultValue={activity?.venue ?? ''} />
                                    <InputError message={errors.venue} />
                                </div>
                                <div className="grid grid-cols-3 gap-3">
                                    <div className="space-y-1">
                                        <Label htmlFor="activity_date">Date</Label>
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
                            </>
                        )}

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

                        <div className="space-y-1">
                            <Label htmlFor="estimated_budget">Estimated Budget (optional)</Label>
                            <Input
                                id="estimated_budget"
                                name="estimated_budget"
                                type="number"
                                min="0"
                                step="0.01"
                                defaultValue={proposal?.estimated_budget ?? ''}
                            />
                            <InputError message={errors.estimated_budget} />
                        </div>

                        <InputError message={errors.activity} />

                        <Button type="submit" className="w-full">
                            Resubmit for Review
                        </Button>
                    </div>
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
