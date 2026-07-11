import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
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

type Membership = {
    id: number;
    position: string;
    position_label: string;
    organization: { id: number; name: string };
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

type ConflictEntry = { name: string; venue: string; activity_date: string; start_time: string; end_time: string; organization: string };
type ConflictResult = { confirmed: ConflictEntry[]; tentative: ConflictEntry[] };

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

type Props = {
    membership: Membership | null;
    current_term_label: string;
    sdgs: SdgOption[];
};

export default function CreateActivityCalendar({ membership, current_term_label, sdgs }: Props) {
    const [activities, setActivities] = useState<ActivityRow[]>([emptyRow()]);
    const [conflicts, setConflicts] = useState<ConflictResult[]>([]);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        router.post(
            '/activity-calendars',
            { activities },
            {
                onError: (errs) => setErrors(errs as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    }

    function updateActivity(index: number, field: keyof ActivityRow, value: string) {
        setActivities((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], [field]: value };

            return next;
        });
    }

    const checkConflicts = useCallback((rows: ActivityRow[]) => {
        const checkable = rows.filter((r) => r.venue && r.activity_date && r.start_time && r.end_time);

        if (checkable.length === 0) {
            setConflicts([]);

            return;
        }

        fetch('/activity-calendars/conflict-check', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ activities: checkable.map((r) => ({ venue: r.venue, activity_date: r.activity_date, start_time: r.start_time, end_time: r.end_time })) }),
        })
            .then((res) => res.json())
            .then((data) => setConflicts(data.results ?? []))
            .catch(() => {});
    }, []);

    useEffect(() => {
        if (debounceTimer.current) {
 clearTimeout(debounceTimer.current); 
}

        debounceTimer.current = setTimeout(() => checkConflicts(activities), 600);

        return () => {
 if (debounceTimer.current) {
 clearTimeout(debounceTimer.current); 
} 
};
    }, [activities, checkConflicts]);

    if (!membership) {
        return (
            <>
                <Head title="Submit Activity Calendar" />
                <div className="mx-auto max-w-2xl p-8">
                    <p className="text-sm text-muted-foreground">
                        You are not bound as an officer of any organization. Contact your adviser
                        before submitting a calendar.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Submit Activity Calendar" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <Heading
                    title="Activity Calendar"
                    description={`Submitting for ${membership.organization.name} as ${membership.position_label}`}
                />

                <form onSubmit={submit} className="space-y-8">
                    {errors.activities && (
                        <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                            {errors.activities}
                        </div>
                    )}

                    {/* Term is a global, admin-controlled setting — shown read-only. */}
                    <div className="grid gap-2">
                        <Label>Term</Label>
                        <p className="text-sm text-muted-foreground">
                            {current_term_label} <span className="text-xs">(set by SDAO — not selectable here)</span>
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

                                {/* Live conflict notice */}
                                {conflicts[i] && (
                                    <>
                                        {conflicts[i].confirmed.length > 0 && (
                                            <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                                ⛔ Confirmed conflict with approved booking:{' '}
                                                {conflicts[i].confirmed.map((c) => `"${c.name}" (${c.organization})`).join(', ')}
                                            </div>
                                        )}
                                        {conflicts[i].tentative.length > 0 && (
                                            <div className="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400">
                                                ⚠ Tentative overlap with pending booking:{' '}
                                                {conflicts[i].tentative.map((c) => `"${c.name}" (${c.organization})`).join(', ')}
                                                {' '}— warning only, you can still submit.
                                            </div>
                                        )}
                                    </>
                                )}

                                <div className="grid gap-2">
                                    <Label>Activity Name</Label>
                                    <Input
                                        value={activity.name}
                                        onChange={(e) => updateActivity(i, 'name', e.target.value)}
                                        required
                                    />
                                    {errors[`activities.${i}.name`] && (
                                        <p className="text-sm text-destructive">{errors[`activities.${i}.name`]}</p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label>Venue</Label>
                                    <Input
                                        value={activity.venue}
                                        onChange={(e) => updateActivity(i, 'venue', e.target.value)}
                                        placeholder="Exact venue name (case-sensitive)"
                                        required
                                    />
                                    {errors[`activities.${i}.venue`] && (
                                        <p className="text-sm text-destructive">{errors[`activities.${i}.venue`]}</p>
                                    )}
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
                                        {errors[`activities.${i}.activity_date`] && (
                                            <p className="text-sm text-destructive">{errors[`activities.${i}.activity_date`]}</p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Start Time</Label>
                                        <Input
                                            type="time"
                                            value={activity.start_time}
                                            onChange={(e) => updateActivity(i, 'start_time', e.target.value)}
                                            required
                                        />
                                        {errors[`activities.${i}.start_time`] && (
                                            <p className="text-sm text-destructive">{errors[`activities.${i}.start_time`]}</p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>End Time</Label>
                                        <Input
                                            type="time"
                                            value={activity.end_time}
                                            onChange={(e) => updateActivity(i, 'end_time', e.target.value)}
                                            required
                                        />
                                        {errors[`activities.${i}.end_time`] && (
                                            <p className="text-sm text-destructive">{errors[`activities.${i}.end_time`]}</p>
                                        )}
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
                                    {errors[`activities.${i}.sdg`] && (
                                        <p className="text-sm text-destructive">{errors[`activities.${i}.sdg`]}</p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label>Participant/Program Assigned</Label>
                                    <Input
                                        value={activity.participant_program_assigned}
                                        onChange={(e) => updateActivity(i, 'participant_program_assigned', e.target.value)}
                                        placeholder="e.g. BSCS — All Year Levels"
                                        required
                                    />
                                    {errors[`activities.${i}.participant_program_assigned`] && (
                                        <p className="text-sm text-destructive">
                                            {errors[`activities.${i}.participant_program_assigned`]}
                                        </p>
                                    )}
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
                                    {errors[`activities.${i}.budget`] && (
                                        <p className="text-sm text-destructive">{errors[`activities.${i}.budget`]}</p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label>Description (optional)</Label>
                                    <Textarea
                                        value={activity.description}
                                        onChange={(e) => updateActivity(i, 'description', e.target.value)}
                                        rows={2}
                                    />
                                    {errors[`activities.${i}.description`] && (
                                        <p className="text-sm text-destructive">{errors[`activities.${i}.description`]}</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing ? 'Submitting…' : 'Submit for Review'}
                    </Button>
                </form>
            </div>
        </>
    );
}

CreateActivityCalendar.layout = {
    breadcrumbs: [{ title: 'Activity Calendars' }, { title: 'New' }],
};
