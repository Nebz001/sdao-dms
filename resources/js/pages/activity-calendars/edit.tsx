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

type TermOption = { value: string; label: string };

type ActivityData = {
    id: number;
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    description: string | null;
};

type CalendarData = {
    term: string;
    activities: ActivityData[];
} | null;

type Props = {
    document: { id: number; title: string };
    calendar: CalendarData;
    terms: TermOption[];
};

type ActivityRow = {
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    description: string;
};

export default function EditActivityCalendar({ document, calendar, terms }: Props) {
    const [activities, setActivities] = useState<ActivityRow[]>(
        calendar?.activities.map((a) => ({
            name: a.name,
            venue: a.venue,
            activity_date: a.activity_date,
            start_time: a.start_time,
            end_time: a.end_time,
            description: a.description ?? '',
        })) ?? [{ name: '', venue: '', activity_date: '', start_time: '', end_time: '', description: '' }],
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
        const form = e.currentTarget;
        const termValue = (form.querySelector('[name="term"]') as HTMLSelectElement | null)?.value ?? '';
        router.put(`/activity-calendars/${document.id}`, {
            term: termValue,
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
                    {/* Term select */}
                    <div className="grid gap-2">
                        <Label htmlFor="term">Term</Label>
                        <Select name="term" defaultValue={calendar?.term} required>
                            <SelectTrigger id="term" className="w-full max-w-xs">
                                <SelectValue placeholder="Select term…" />
                            </SelectTrigger>
                            <SelectContent>
                                {terms.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Activities */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-medium">Activities</h3>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setActivities((prev) => [
                                        ...prev,
                                        { name: '', venue: '', activity_date: '', start_time: '', end_time: '', description: '' },
                                    ])
                                }
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
