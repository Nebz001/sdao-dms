import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
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

type SdgOption = { value: string; label: string };

type ActivityData = {
    id: number;
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    description: string | null;
    sdg: string | null;
    participant_program_assigned: string | null;
    budget: string | null;
};

type CalendarData = {
    term_label: string;
    activities: ActivityData[];
} | null;

type Props = {
    document: { id: number; title: string };
    calendar: CalendarData;
    sdgs: SdgOption[];
};

type ActivityRow = {
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    description: string;
    sdg: string;
    participant_program_assigned: string;
    budget: string;
};

const emptyRow = (): ActivityRow => ({
    name: '',
    venue: '',
    activity_date: '',
    start_time: '',
    end_time: '',
    description: '',
    sdg: '',
    participant_program_assigned: '',
    budget: '',
});

export default function EditActivityCalendar({ document, calendar, sdgs }: Props) {
    const [activities, setActivities] = useState<ActivityRow[]>(
        calendar?.activities.map((a) => ({
            name: a.name,
            venue: a.venue,
            activity_date: a.activity_date,
            start_time: a.start_time,
            end_time: a.end_time,
            description: a.description ?? '',
            sdg: a.sdg ?? '',
            participant_program_assigned: a.participant_program_assigned ?? '',
            budget: a.budget ?? '',
        })) ?? [emptyRow()],
    );

    function updateActivity(index: number, field: keyof ActivityRow, value: string) {
        setActivities((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], [field]: value };

            return next;
        });
    }

    function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        // Term is frozen at original submission (Phase 2 item 6) — it is
        // never resent or re-derived from user input on resubmit.
        router.put(`/activity-calendars/${document.id}`, {
            activities,
        });
    }

    return (
        <>
            <Head title="Edit Activity Calendar" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <Heading
                    title="Edit & Resubmit Activity Calendar"
                    description="Update the activities below and resubmit for SDAO review."
                />

                <form onSubmit={handleSubmit} className="space-y-8">
                    {/* Term is frozen at original submission — shown read-only. */}
                    <div className="grid gap-2">
                        <Label>Term</Label>
                        <p className="text-sm text-muted-foreground">
                            {calendar?.term_label} <span className="text-xs">(set at original submission — cannot be changed)</span>
                        </p>
                    </div>

                    {/* Activities */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-medium">Activities</h3>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setActivities((prev) => [...prev, emptyRow()])}
                            >
                                + Add Activity
                            </Button>
                        </div>

                        {activities.map((activity, i) => (
                            <div key={i} className="rounded-lg border p-4 space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-muted-foreground">Activity {i + 1}</span>
                                    {activities.length > 1 && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setActivities((prev) => prev.filter((_, idx) => idx !== i))}
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label>Activity Name</Label>
                                    <Input
                                        value={activity.name}
                                        onChange={(e) => updateActivity(i, 'name', e.target.value)}
                                        required
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Venue</Label>
                                    <Input
                                        value={activity.venue}
                                        onChange={(e) => updateActivity(i, 'venue', e.target.value)}
                                        placeholder="Exact venue name"
                                        required
                                    />
                                </div>

                                <div className="grid grid-cols-3 gap-3">
                                    <div className="grid gap-2">
                                        <Label>Date</Label>
                                        <Input
                                            type="date"
                                            value={activity.activity_date}
                                            onChange={(e) => updateActivity(i, 'activity_date', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Start Time</Label>
                                        <Input
                                            type="time"
                                            value={activity.start_time}
                                            onChange={(e) => updateActivity(i, 'start_time', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>End Time</Label>
                                        <Input
                                            type="time"
                                            value={activity.end_time}
                                            onChange={(e) => updateActivity(i, 'end_time', e.target.value)}
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`sdg-${i}`}>SDG</Label>
                                    <Select
                                        value={activity.sdg}
                                        onValueChange={(value) => updateActivity(i, 'sdg', value)}
                                        required
                                    >
                                        <SelectTrigger id={`sdg-${i}`} className="w-full">
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
                                </div>

                                <div className="grid gap-2">
                                    <Label>Participant/Program Assigned</Label>
                                    <Input
                                        value={activity.participant_program_assigned}
                                        onChange={(e) => updateActivity(i, 'participant_program_assigned', e.target.value)}
                                        placeholder="e.g. BSCS — All Year Levels"
                                        required
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Budget</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={activity.budget}
                                        onChange={(e) => updateActivity(i, 'budget', e.target.value)}
                                        required
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Description (optional)</Label>
                                    <Textarea
                                        value={activity.description}
                                        onChange={(e) => updateActivity(i, 'description', e.target.value)}
                                        rows={2}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>

                    <Button type="submit">Save &amp; Resubmit</Button>
                </form>
            </div>
        </>
    );
}

EditActivityCalendar.layout = {
    breadcrumbs: [{ title: 'Activity Calendars' }, { title: 'Edit' }],
};
