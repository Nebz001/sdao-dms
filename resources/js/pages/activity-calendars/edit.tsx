import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { formatLongDate } from '@/components/document-view/format';
import {
    FixFooter,
    FixItemBox,
    FixProvider,
    FixShell,
    FixStrip,
    FixSummary,
    LockedField,
    LockedGrid,
    useFix,
} from '@/components/fix-resend';
import { FormCard, FormFooter, FormStrip } from '@/components/form-shell';
import FormSubmitConfirm from '@/components/form-submit-confirm';
import InputError from '@/components/input-error';
import SdgCheckboxGroup from '@/components/sdg-checkbox-group';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { hasChanged } from '@/lib/fix-resend';
import type { FixProps } from '@/lib/fix-resend';
import { todayDateString } from '@/lib/utils';

type SdgOption = { value: string; label: string };

type ActivityData = {
    id: number;
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    description: string | null;
    sdg: string[];
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
    fix: FixProps;
};

type ActivityRow = {
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    description: string;
    sdg: string[];
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
    sdg: [],
    participant_program_assigned: '',
    budget: '',
});

function toRow(activity: ActivityData): ActivityRow {
    return {
        name: activity.name,
        venue: activity.venue,
        activity_date: activity.activity_date,
        start_time: activity.start_time,
        end_time: activity.end_time,
        description: activity.description ?? '',
        sdg: activity.sdg,
        participant_program_assigned:
            activity.participant_program_assigned ?? '',
        budget: activity.budget ?? '',
    };
}

export default function EditActivityCalendar({ fix, ...props }: Props) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Activity Calendars' },
                { title: 'Fix and Resend' },
            ],
            trailParent: { title: fix.back.title, href: fix.back.href },
        });
    }, [fix.back.title, fix.back.href]);

    return (
        <FixProvider fix={fix}>
            <Head title="Fix and Resend" />
            <CalendarFix {...props} />
        </FixProvider>
    );
}

function CalendarFix({ document, calendar, sdgs }: Omit<Props, 'fix'>) {
    const { fix, isFlagged } = useFix();
    const baseRows = calendar?.activities.map(toRow) ?? [];
    const [activities, setActivities] = useState<ActivityRow[]>(
        baseRows.length > 0
            ? baseRows.map((row, i) => ({
                  ...row,
                  ...((fix.draft?.[`activity_${i}`] as
                      | Partial<ActivityRow>
                      | undefined) ?? {}),
              }))
            : [emptyRow()],
    );
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const minDate = todayDateString();
    const formId = `edit-calendar-form-${document.id}`;

    function updateActivity<K extends keyof ActivityRow>(
        index: number,
        field: K,
        value: ActivityRow[K],
    ) {
        setActivities((prev) =>
            prev.map((row, i) =>
                i === index ? { ...row, [field]: value } : row,
            ),
        );
    }

    function submit(event?: React.FormEvent<HTMLFormElement>) {
        event?.preventDefault();
        setProcessing(true);
        setErrors({});

        // On a Fix and Resend only the flagged activities are sent, by their
        // position; the server keeps every other row exactly as it was.
        // Term is frozen at original submission, never resent.
        const payload = fix.active
            ? Object.fromEntries(
                  fix.items.map((item) => [
                      Number(item.key.replace('activity_', '')),
                      activities[Number(item.key.replace('activity_', ''))],
                  ]),
              )
            : activities;

        router.put(
            `/activity-calendars/${document.id}`,
            { activities: payload },
            {
                onError: (errs) => setErrors(errs as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    }

    function rowFields(activity: ActivityRow, i: number) {
        return (
            <div className="space-y-3">
                <div className="grid gap-2">
                    <Label htmlFor={`name-${i}`}>Activity Name</Label>
                    <Input
                        id={`name-${i}`}
                        value={activity.name}
                        onChange={(e) =>
                            updateActivity(i, 'name', e.target.value)
                        }
                        required
                    />
                    <InputError message={errors[`activities.${i}.name`]} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`venue-${i}`}>Venue</Label>
                    <Input
                        id={`venue-${i}`}
                        value={activity.venue}
                        onChange={(e) =>
                            updateActivity(i, 'venue', e.target.value)
                        }
                        placeholder="Exact venue name"
                        required
                    />
                    <InputError
                        message={
                            errors[`activities.${i}.venue`] ??
                            errors[`activities.${i}`]
                        }
                    />
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor={`date-${i}`}>Date</Label>
                        <Input
                            id={`date-${i}`}
                            type="date"
                            value={activity.activity_date}
                            min={minDate}
                            onChange={(e) =>
                                updateActivity(
                                    i,
                                    'activity_date',
                                    e.target.value,
                                )
                            }
                            required
                        />
                        <InputError
                            message={errors[`activities.${i}.activity_date`]}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`start-${i}`}>Start Time</Label>
                        <Input
                            id={`start-${i}`}
                            type="time"
                            value={activity.start_time}
                            onChange={(e) =>
                                updateActivity(i, 'start_time', e.target.value)
                            }
                            required
                        />
                        <InputError
                            message={errors[`activities.${i}.start_time`]}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`end-${i}`}>End Time</Label>
                        <Input
                            id={`end-${i}`}
                            type="time"
                            value={activity.end_time}
                            onChange={(e) =>
                                updateActivity(i, 'end_time', e.target.value)
                            }
                            required
                        />
                        <InputError
                            message={errors[`activities.${i}.end_time`]}
                        />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label>SDG (select one or more)</Label>
                    <SdgCheckboxGroup
                        idPrefix={`sdg-${i}`}
                        options={sdgs}
                        selected={activity.sdg}
                        onChange={(next) => updateActivity(i, 'sdg', next)}
                    />
                    <InputError message={errors[`activities.${i}.sdg`]} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`participants-${i}`}>
                        Participant/Program Assigned
                    </Label>
                    <Input
                        id={`participants-${i}`}
                        value={activity.participant_program_assigned}
                        onChange={(e) =>
                            updateActivity(
                                i,
                                'participant_program_assigned',
                                e.target.value,
                            )
                        }
                        placeholder="e.g. BSCS — All Year Levels"
                        required
                    />
                    <InputError
                        message={
                            errors[
                                `activities.${i}.participant_program_assigned`
                            ]
                        }
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`budget-${i}`}>Budget</Label>
                    <Input
                        id={`budget-${i}`}
                        type="number"
                        step="0.01"
                        min="0"
                        value={activity.budget}
                        onChange={(e) =>
                            updateActivity(i, 'budget', e.target.value)
                        }
                        required
                    />
                    <InputError message={errors[`activities.${i}.budget`]} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`description-${i}`}>
                        Description (optional)
                    </Label>
                    <Textarea
                        id={`description-${i}`}
                        value={activity.description}
                        onChange={(e) =>
                            updateActivity(i, 'description', e.target.value)
                        }
                        rows={2}
                    />
                    <InputError
                        message={errors[`activities.${i}.description`]}
                    />
                </div>
            </div>
        );
    }

    function lockedRow(row: ActivityRow) {
        return (
            <LockedGrid>
                <LockedField label="Venue" value={row.venue} />
                <LockedField
                    label="Date"
                    value={formatLongDate(row.activity_date)}
                />
                <LockedField
                    label="Time"
                    value={`${row.start_time} – ${row.end_time}`}
                />
                <LockedField
                    label="Participant/Program"
                    value={row.participant_program_assigned}
                />
                <LockedField
                    label="Budget"
                    value={
                        row.budget &&
                        `₱${Number(row.budget).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
                    }
                />
                <LockedField
                    label="SDG"
                    value={sdgs
                        .filter((s) => row.sdg.includes(s.value))
                        .map((s) => s.label)
                        .join(', ')}
                />
                {row.description && (
                    <LockedField label="Description" value={row.description} />
                )}
            </LockedGrid>
        );
    }

    return (
        <FixShell fix={fix}>
            <form onSubmit={submit} id={formId}>
                <FormCard>
                    {fix.active ? (
                        <>
                            <FixStrip />
                            <FixSummary />
                        </>
                    ) : (
                        <>
                            <FormStrip
                                left={
                                    <>
                                        Activity Calendar for{' '}
                                        <strong className="font-semibold text-foreground">
                                            {fix.organization}
                                        </strong>
                                    </>
                                }
                            />
                            <FixSummary />
                        </>
                    )}
                    {(errors.activities || errors.locked || errors.flagged) && (
                        <p
                            role="alert"
                            className="border-b bg-destructive/10 px-5 py-3 text-sm text-destructive-foreground sm:px-6"
                        >
                            {errors.activities} {errors.locked} {errors.flagged}
                        </p>
                    )}

                    <section
                        aria-label="Activities"
                        className="space-y-4 px-5 py-5 sm:px-6"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 className="text-base font-semibold">
                                Activities
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                {calendar?.term_label} (set at original
                                submission, can’t be changed)
                            </p>
                        </div>

                        {activities.map((activity, i) => (
                            <div key={i} className="space-y-3">
                                {fix.active ? (
                                    <FixItemBox
                                        sectionKey={`activity_${i}`}
                                        heading={`Activity ${i + 1}: ${baseRows[i]?.name ?? activity.name}`}
                                        changed={
                                            baseRows[i]
                                                ? hasChanged(
                                                      baseRows[i],
                                                      activity,
                                                  )
                                                : false
                                        }
                                        locked={
                                            <div className="space-y-2 rounded-lg border p-4">
                                                <h3 className="text-sm font-semibold">
                                                    Activity {i + 1}:{' '}
                                                    {baseRows[i]?.name}
                                                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                        Locked
                                                    </span>
                                                </h3>
                                                {lockedRow(
                                                    baseRows[i] ?? activity,
                                                )}
                                            </div>
                                        }
                                    >
                                        {rowFields(activity, i)}
                                    </FixItemBox>
                                ) : (
                                    <div className="space-y-3 rounded-lg border p-4">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium text-muted-foreground">
                                                Activity {i + 1}
                                            </span>
                                            {activities.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setActivities((prev) =>
                                                            prev.filter(
                                                                (_, idx) =>
                                                                    idx !== i,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                        {rowFields(activity, i)}
                                    </div>
                                )}
                            </div>
                        ))}

                        {!fix.active && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setActivities((prev) => [
                                        ...prev,
                                        emptyRow(),
                                    ])
                                }
                            >
                                + Add Activity
                            </Button>
                        )}
                    </section>

                    {fix.active ? (
                        <FixFooter
                            formId={formId}
                            processing={processing}
                            collect={() =>
                                Object.fromEntries(
                                    fix.items
                                        .filter((item) => isFlagged(item.key))
                                        .map((item) => [
                                            item.key,
                                            activities[
                                                Number(
                                                    item.key.replace(
                                                        'activity_',
                                                        '',
                                                    ),
                                                )
                                            ],
                                        ]),
                                )
                            }
                            title="Resend this calendar?"
                            description={`It goes back to ${fix.role}, who asked for these changes.`}
                        />
                    ) : (
                        <FormFooter
                            status={`${fix.role} sees it again as soon as you resend.`}
                        >
                            <FormSubmitConfirm
                                formId={formId}
                                processing={processing}
                                title="Resend this calendar?"
                                description={`It goes back to ${fix.role}, who returned it.`}
                                confirmLabel={fix.resendLabel}
                            >
                                {fix.resendLabel}
                            </FormSubmitConfirm>
                        </FormFooter>
                    )}
                </FormCard>
            </form>
        </FixShell>
    );
}

EditActivityCalendar.layout = {
    breadcrumbs: [{ title: 'Activity Calendars' }, { title: 'Fix and Resend' }],
    columnWidth: '3xl',
};
