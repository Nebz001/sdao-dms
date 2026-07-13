import { Form, Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import * as activityProposals from '@/routes/activity-proposals';

type Membership = {
    id: number;
    position: string;
    position_label: string;
    organization: { id: number; name: string };
} | null;

type Term = { value: string; label: string };
type CalendarMode = { value: string; label: string };
type OptionItem = { value: string; label: string };

type OnCalendarActivity = {
    id: number;
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
};

type ConflictItem = {
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    organization: string;
};

type Props = {
    membership: Membership;
    terms: Term[];
    calendarModes: CalendarMode[];
    activityNatures: OptionItem[];
    activityTypes: OptionItem[];
    sdgs: OptionItem[];
};

export default function CreateActivityProposal({
    membership,
    terms,
    calendarModes,
    activityNatures,
    activityTypes,
    sdgs,
}: Props) {
    const [calendarMode, setCalendarMode] = useState('');
    const [onCalendarActivities, setOnCalendarActivities] = useState<OnCalendarActivity[]>([]);
    const [loadingActivities, setLoadingActivities] = useState(false);

    // Off-calendar fields
    const [venue, setVenue] = useState('');
    const [activityDate, setActivityDate] = useState('');
    const [startTime, setStartTime] = useState('');
    const [endTime, setEndTime] = useState('');

    // Partner Organization(s)/School(s)/RSO — Phase 2 item 7 slice 4a
    const [partnerOrgs, setPartnerOrgs] = useState<string[]>(['']);

    // Conflict state (off-calendar live preview)
    const [confirmedConflicts, setConfirmedConflicts] = useState<ConflictItem[]>([]);
    const [tentativeConflicts, setTentativeConflicts] = useState<ConflictItem[]>([]);
    const conflictTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (calendarMode !== 'on_calendar') {
return;
}

        fetch(activityProposals.onCalendarActivities().url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((r) => r.json())
            .then((data) => {
                setOnCalendarActivities(data.activities ?? []);
                setLoadingActivities(false);
            })
            .catch(() => setLoadingActivities(false));
    }, [calendarMode]);

    function scheduleConflictCheck() {
        if (!venue || !activityDate || !startTime || !endTime) {
            return;
        }

        if (conflictTimer.current) {
clearTimeout(conflictTimer.current);
}

        conflictTimer.current = setTimeout(() => {
            fetch(activityProposals.conflictCheck().url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                    ),
                },
                body: JSON.stringify({ venue, activity_date: activityDate, start_time: startTime, end_time: endTime }),
            })
                .then((r) => r.json())
                .then((data) => {
                    setConfirmedConflicts(data.confirmed ?? []);
                    setTentativeConflicts(data.tentative ?? []);
                })
                .catch(() => {});
        }, 600);
    }

    useEffect(scheduleConflictCheck, [venue, activityDate, startTime, endTime]);

    if (!membership) {
        return (
            <div className="mx-auto max-w-xl p-8">
                <p className="text-muted-foreground">
                    You must be an active officer of an organization to submit a proposal.
                </p>
            </div>
        );
    }

    return (
        <>
            <Head title="New Activity Proposal" />

            <div className="mx-auto max-w-xl space-y-6 p-8">
                <h1 className="text-xl font-semibold">New Activity Proposal</h1>
                <p className="text-sm text-muted-foreground">
                    <span className="font-medium">Name of RSO:</span> {membership.organization.name}
                </p>

                <Form action={activityProposals.store().url} method="post">
                    {({ processing, errors }) => (
                    <div className="space-y-4">
                        {/* Calendar mode */}
                        <div className="space-y-1">
                            <Label htmlFor="calendar_mode">Activity Type</Label>
                            <Select
                            name="calendar_mode"
                            value={calendarMode}
                            onValueChange={(v) => {
                                setCalendarMode(v);

                                if (v === 'on_calendar') {
                                    setLoadingActivities(true);
                                    setOnCalendarActivities([]);
                                }
                            }}
                        >
                                <SelectTrigger id="calendar_mode">
                                    <SelectValue placeholder="Select…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {calendarModes.map((m) => (
                                        <SelectItem key={m.value} value={m.value}>
                                            {m.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.calendar_mode} />
                        </div>

                        {/* On-calendar: picker */}
                        {calendarMode === 'on_calendar' && (
                            <div className="space-y-1">
                                <Label htmlFor="calendar_activity_id">Select Calendar Activity</Label>
                                {loadingActivities ? (
                                    <p className="text-sm text-muted-foreground">Loading activities…</p>
                                ) : onCalendarActivities.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No approved calendar activities found for your organization.
                                    </p>
                                ) : (
                                    <Select name="calendar_activity_id">
                                        <SelectTrigger id="calendar_activity_id">
                                            <SelectValue placeholder="Select an activity…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {onCalendarActivities.map((a) => (
                                                <SelectItem key={a.id} value={String(a.id)}>
                                                    {a.name} — {a.venue} ({a.activity_date})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                                <InputError message={errors.calendar_activity_id} />
                            </div>
                        )}

                        {/* Off-calendar: activity details */}
                        {calendarMode === 'off_calendar' && (
                            <>
                                <div className="space-y-1">
                                    <Label htmlFor="title">Title of Activity</Label>
                                    <Input id="title" name="title" placeholder="e.g. Leadership Summit" />
                                    <InputError message={errors.title} />
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="venue">Venue</Label>
                                    <Input
                                        id="venue"
                                        name="venue"
                                        value={venue}
                                        onChange={(e) => setVenue(e.target.value)}
                                    />
                                    <InputError message={errors.venue} />
                                </div>

                                <div className="grid grid-cols-3 gap-3">
                                    <div className="space-y-1">
                                        <Label htmlFor="activity_date">Date of Activity</Label>
                                        <Input
                                            id="activity_date"
                                            name="activity_date"
                                            type="date"
                                            value={activityDate}
                                            onChange={(e) => setActivityDate(e.target.value)}
                                        />
                                        <InputError message={errors.activity_date} />
                                    </div>
                                    <div className="space-y-1">
                                        <Label htmlFor="start_time">Start</Label>
                                        <Input
                                            id="start_time"
                                            name="start_time"
                                            type="time"
                                            value={startTime}
                                            onChange={(e) => setStartTime(e.target.value)}
                                        />
                                        <InputError message={errors.start_time} />
                                    </div>
                                    <div className="space-y-1">
                                        <Label htmlFor="end_time">End</Label>
                                        <Input
                                            id="end_time"
                                            name="end_time"
                                            type="time"
                                            value={endTime}
                                            onChange={(e) => setEndTime(e.target.value)}
                                        />
                                        <InputError message={errors.end_time} />
                                    </div>
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="term">Term</Label>
                                    <Select name="term">
                                        <SelectTrigger id="term">
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
                                    <InputError message={errors.term} />
                                </div>

                                {/* Live conflict preview */}
                                {confirmedConflicts.length > 0 && (
                                    <Card className="border-destructive bg-destructive/5">
                                        <CardContent className="pt-4">
                                            <p className="mb-2 text-sm font-medium text-destructive">
                                                Venue conflict — this slot is already booked:
                                            </p>
                                            {confirmedConflicts.map((c, i) => (
                                                <p key={i} className="text-sm text-destructive">
                                                    {c.name} ({c.organization}) · {c.start_time}–{c.end_time}
                                                </p>
                                            ))}
                                        </CardContent>
                                    </Card>
                                )}
                                {tentativeConflicts.length > 0 && confirmedConflicts.length === 0 && (
                                    <Card className="border-amber-500 bg-amber-50 dark:border-amber-500/60 dark:bg-amber-950/40">
                                        <CardContent className="pt-4">
                                            <p className="mb-2 text-sm font-medium text-amber-700 dark:text-amber-400">
                                                Possible conflict — another pending activity overlaps this slot:
                                            </p>
                                            {tentativeConflicts.map((c, i) => (
                                                <p key={i} className="text-sm text-amber-700 dark:text-amber-400">
                                                    {c.name} ({c.organization}) · {c.start_time}–{c.end_time}
                                                </p>
                                            ))}
                                        </CardContent>
                                    </Card>
                                )}
                            </>
                        )}

                        {/* Exact field corrections (Phase 2 item 7 slice 4a) —
                            apply regardless of on/off-calendar mode, since
                            these are proposal-level classification/budget
                            data, not schedule data. */}
                        {calendarMode !== '' && (
                            <>
                                <div className="space-y-1">
                                    <Label htmlFor="activity_nature">Nature of Activity</Label>
                                    <Select name="activity_nature">
                                        <SelectTrigger id="activity_nature">
                                            <SelectValue placeholder="Select nature…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {activityNatures.map((n) => (
                                                <SelectItem key={n.value} value={n.value}>
                                                    {n.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.activity_nature} />
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="activity_type">Type of Activity</Label>
                                    <Select name="activity_type">
                                        <SelectTrigger id="activity_type">
                                            <SelectValue placeholder="Select type…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {activityTypes.map((t) => (
                                                <SelectItem key={t.value} value={t.value}>
                                                    {t.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.activity_type} />
                                </div>

                                <div className="space-y-1">
                                    <div className="flex items-center justify-between">
                                        <Label>Partner Organization(s)/School(s)/RSO</Label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setPartnerOrgs((prev) => [...prev, ''])}
                                        >
                                            + Add
                                        </Button>
                                    </div>
                                    {partnerOrgs.map((org, i) => (
                                        <div key={i} className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <Input
                                                    name={`partner_organizations[${i}]`}
                                                    value={org}
                                                    onChange={(e) =>
                                                        setPartnerOrgs((prev) => {
                                                            const next = [...prev];
                                                            next[i] = e.target.value;

                                                            return next;
                                                        })
                                                    }
                                                    placeholder="Organization, School, or RSO name"
                                                />
                                                {partnerOrgs.length > 1 && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setPartnerOrgs((prev) => prev.filter((_, idx) => idx !== i))}
                                                    >
                                                        Remove
                                                    </Button>
                                                )}
                                            </div>
                                            <InputError message={errors[`partner_organizations.${i}`]} />
                                        </div>
                                    ))}
                                    <InputError message={errors.partner_organizations} />
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="target_sdg">Target SDG</Label>
                                    <Select name="target_sdg">
                                        <SelectTrigger id="target_sdg">
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
                                    <InputError message={errors.target_sdg} />
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="proposed_budget">Proposed Budget</Label>
                                    <Input
                                        id="proposed_budget"
                                        name="proposed_budget"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                    <InputError message={errors.proposed_budget} />
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="budget_source">Budget Source</Label>
                                    <Input id="budget_source" name="budget_source" placeholder="e.g. Org funds, sponsorship…" />
                                    <InputError message={errors.budget_source} />
                                </div>
                            </>
                        )}

                        <InputError message={errors.activity} />

                        <Button
                            type="submit"
                            disabled={!calendarMode}
                            loading={processing}
                            loadingText="Continuing…"
                            className="w-full"
                        >
                            Continue to Narrative
                        </Button>
                    </div>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateActivityProposal.layout = {
    breadcrumbs: [
        { title: 'Activity Proposals', href: '/activity-proposals' },
        { title: 'New' },
    ],
};
