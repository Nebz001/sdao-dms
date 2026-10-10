import { Form, Head } from '@inertiajs/react';
import { ArrowRight, Lock, MapPin } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ActivityPicker from '@/components/activity-picker';
import type { PickerActivity } from '@/components/activity-picker';
import AttachmentDropZone from '@/components/attachment-drop-zone';
import type { AttachmentSlotDef } from '@/components/attachment-slot-field';
import {
    FocusFirstError,
    FormCard,
    FormField,
    FormFooter,
    FormSection,
    FormShell,
    FormStrip,
} from '@/components/form-shell';
import PageNotice from '@/components/page-notice';
import PartnerOrganizationChips from '@/components/partner-organization-chips';
import SdgChipGroup from '@/components/sdg-chip-group';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupOption } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { cn, todayDateString } from '@/lib/utils';
import * as activityProposals from '@/routes/activity-proposals';
import * as calendarRoutes from '@/routes/calendar';

type Membership = {
    id: number;
    position: string;
    position_label: string;
    organization: { id: number; name: string; school: string | null };
} | null;

type CalendarMode = { value: string; label: string };
type OptionItem = { value: string; label: string };

type OnCalendarActivity = {
    id: number;
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    term_label: string;
    locked: boolean;
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
    current_term_label: string;
    calendarModes: CalendarMode[];
    activityNatures: OptionItem[];
    activityTypes: OptionItem[];
    sdgs: OptionItem[];
    budgetSources: OptionItem[];
    attachmentSlots: AttachmentSlotDef[];
};

/** The registry label carries the instruction; the form shows it as helper text. */
const SLOT_COPY: Record<string, { label?: string; helper?: string }> = {
    request_letter: {
        label: 'Request letter',
        helper: 'Must include the rationale, objectives, and program',
    },
};

const STEPS = ['Request form', 'Narrative'];

export default function CreateActivityProposal({
    membership,
    current_term_label,
    calendarModes,
    activityNatures,
    activityTypes,
    sdgs,
    budgetSources,
    attachmentSlots,
}: Props) {
    const minDate = todayDateString();

    // "Yes, on calendar" is the default, so the whole form is visible on open.
    // Every field below lives in page state or in an input that stays mounted
    // across a switch, so changing the mode never wipes what was typed.
    const [calendarMode, setCalendarMode] = useState('on_calendar');
    const [calendarActivityId, setCalendarActivityId] = useState('');
    const [onCalendarActivities, setOnCalendarActivities] = useState<
        OnCalendarActivity[]
    >([]);
    const [loadingActivities, setLoadingActivities] = useState(true);

    const [title, setTitle] = useState('');
    const [venue, setVenue] = useState('');
    const [activityDate, setActivityDate] = useState('');
    const [startTime, setStartTime] = useState('');
    const [endTime, setEndTime] = useState('');

    const [activityNature, setActivityNature] = useState('');
    const [activityNatureOther, setActivityNatureOther] = useState('');
    const [activityType, setActivityType] = useState('');
    const [activityTypeOther, setActivityTypeOther] = useState('');
    const [targetSdg, setTargetSdg] = useState<string[]>([]);
    const [budgetSource, setBudgetSource] = useState('');

    const [confirmedConflicts, setConfirmedConflicts] = useState<ConflictItem[]>([]);
    const [tentativeConflicts, setTentativeConflicts] = useState<ConflictItem[]>([]);
    const conflictTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const isOnCalendar = calendarMode === 'on_calendar';
    const pickerActivities: PickerActivity[] = onCalendarActivities.map((a) => ({
        id: String(a.id),
        title: a.name,
        date: a.activity_date,
        start_time: a.start_time,
        end_time: a.end_time,
        venue: a.venue,
        group: a.term_label,
        disabledReason: a.locked ? 'Already proposed' : null,
    }));

    useEffect(() => {
        fetch(activityProposals.onCalendarActivities().url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((r) => r.json())
            .then((data) => {
                setOnCalendarActivities(data.activities ?? []);
                setLoadingActivities(false);
            })
            .catch(() => setLoadingActivities(false));
    }, []);

    useEffect(() => {
        if (isOnCalendar || !venue || !activityDate || !startTime || !endTime) {
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
                body: JSON.stringify({
                    venue,
                    activity_date: activityDate,
                    start_time: startTime,
                    end_time: endTime,
                }),
            })
                .then((r) => r.json())
                .then((data) => {
                    setConfirmedConflicts(data.confirmed ?? []);
                    setTentativeConflicts(data.tentative ?? []);
                })
                .catch(() => {});
        }, 600);

        return () => {
            if (conflictTimer.current) {
                clearTimeout(conflictTimer.current);
            }
        };
    }, [isOnCalendar, venue, activityDate, startTime, endTime]);

    if (!membership) {
        return (
            <>
                <Head title="New Activity Proposal" />
                <FormShell
                    title="New Activity Proposal"
                    subtitle="Fill in the request form, then write the narrative."
                >
                    <PageNotice tone="info">
                        You must be an active officer of an organization to
                        submit a proposal.
                    </PageNotice>
                </FormShell>
            </>
        );
    }

    const orgName = membership.organization.name;

    return (
        <>
            <Head title="New Activity Proposal" />

            <FormShell
                title="New Activity Proposal"
                subtitle="Fill in the request form, then write the narrative. It goes to your adviser after step 2."
                steps={STEPS}
                currentStep={1}
            >
                <Form action={activityProposals.store().url} method="post">
                    {({ processing, errors }) => (
                        <FormCard>
                            <FocusFirstError errors={errors} />
                            <FormStrip
                                left={
                                    <>
                                        Filing for{' '}
                                        <strong className="font-semibold text-foreground">
                                            {orgName}
                                        </strong>
                                    </>
                                }
                                right={membership.organization.school}
                            />

                            <FormSection title="About the activity">
                                <fieldset className="grid gap-1.5">
                                    <legend className="mb-1.5 text-sm leading-snug font-medium">
                                        Is this activity in your activity
                                        calendar?
                                    </legend>
                                    <RadioGroup className="grid-cols-1 sm:grid-cols-2">
                                        {calendarModes.map((mode) => (
                                            <RadioGroupOption
                                                key={mode.value}
                                                name="calendar_mode"
                                                value={mode.value}
                                                checked={calendarMode === mode.value}
                                                onChange={() =>
                                                    setCalendarMode(mode.value)
                                                }
                                                title={
                                                    mode.value === 'on_calendar'
                                                        ? 'Yes, on calendar'
                                                        : 'No, off calendar'
                                                }
                                                description={
                                                    mode.value === 'on_calendar'
                                                        ? 'It’s already in your approved term plan'
                                                        : 'A new activity not in your term plan'
                                                }
                                            />
                                        ))}
                                    </RadioGroup>
                                    {errors.calendar_mode && (
                                        <p className="text-sm text-destructive">
                                            {errors.calendar_mode}
                                        </p>
                                    )}
                                </fieldset>

                                {isOnCalendar ? (
                                    <FormField
                                        id="calendar_activity_id"
                                        label="Calendar activity"
                                        error={errors.calendar_activity_id}
                                    >
                                        {(aria) =>
                                            loadingActivities ? (
                                                <Skeleton
                                                    className="h-9 w-full"
                                                    role="status"
                                                    aria-label="Loading calendar activities"
                                                />
                                            ) : onCalendarActivities.length === 0 ? (
                                                <PageNotice
                                                    tone="warning"
                                                    action={
                                                        <Button
                                                            type="button"
                                                            variant="link"
                                                            size="sm"
                                                            className="h-auto p-0 font-semibold text-warning-foreground underline"
                                                            onClick={() =>
                                                                setCalendarMode(
                                                                    'off_calendar',
                                                                )
                                                            }
                                                        >
                                                            Choose off calendar
                                                            instead
                                                        </Button>
                                                    }
                                                >
                                                    No approved calendar
                                                    activities found for{' '}
                                                    {orgName}.
                                                </PageNotice>
                                            ) : (
                                                <ActivityPicker
                                                    id="calendar_activity_id"
                                                    name="calendar_activity_id"
                                                    activities={pickerActivities}
                                                    value={calendarActivityId}
                                                    onChange={setCalendarActivityId}
                                                    placeholder="Choose from your activity calendar"
                                                    helper="The date, time, and venue come from your approved activity calendar"
                                                    invalid={Boolean(errors.calendar_activity_id)}
                                                    describedBy={aria['aria-describedby']}
                                                />
                                            )
                                        }
                                    </FormField>
                                ) : (
                                    <FormField
                                        id="title"
                                        label="Title of activity"
                                        error={errors.title}
                                    >
                                        {(aria) => (
                                            <Input
                                                id="title"
                                                name="title"
                                                value={title}
                                                onChange={(e) =>
                                                    setTitle(e.target.value)
                                                }
                                                placeholder="e.g. Leadership Summit"
                                                {...aria}
                                            />
                                        )}
                                    </FormField>
                                )}

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="activity_nature"
                                        label="Nature of activity"
                                        error={errors.activity_nature}
                                    >
                                        {(aria) => (
                                            <Select
                                                name="activity_nature"
                                                value={activityNature}
                                                onValueChange={setActivityNature}
                                            >
                                                <SelectTrigger
                                                    id="activity_nature"
                                                    className="w-full"
                                                    {...aria}
                                                >
                                                    <SelectValue placeholder="Select nature" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {activityNatures.map((n) => (
                                                        <SelectItem
                                                            key={n.value}
                                                            value={n.value}
                                                        >
                                                            {n.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </FormField>

                                    <FormField
                                        id="activity_type"
                                        label="Type of activity"
                                        error={errors.activity_type}
                                    >
                                        {(aria) => (
                                            <Select
                                                name="activity_type"
                                                value={activityType}
                                                onValueChange={setActivityType}
                                            >
                                                <SelectTrigger
                                                    id="activity_type"
                                                    className="w-full"
                                                    {...aria}
                                                >
                                                    <SelectValue placeholder="Select type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {activityTypes.map((t) => (
                                                        <SelectItem
                                                            key={t.value}
                                                            value={t.value}
                                                        >
                                                            {t.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </FormField>

                                    {activityNature === 'others' && (
                                        <FormField
                                            id="activity_nature_other"
                                            label="Please specify the nature"
                                            error={errors.activity_nature_other}
                                        >
                                            {(aria) => (
                                                <Input
                                                    id="activity_nature_other"
                                                    name="activity_nature_other"
                                                    value={activityNatureOther}
                                                    onChange={(e) =>
                                                        setActivityNatureOther(
                                                            e.target.value,
                                                        )
                                                    }
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                    )}
                                    {activityType === 'others' && (
                                        <FormField
                                            id="activity_type_other"
                                            label="Please specify the type"
                                            error={errors.activity_type_other}
                                            className={cn(
                                                activityNature !== 'others' &&
                                                    'sm:col-start-2',
                                            )}
                                        >
                                            {(aria) => (
                                                <Input
                                                    id="activity_type_other"
                                                    name="activity_type_other"
                                                    value={activityTypeOther}
                                                    onChange={(e) =>
                                                        setActivityTypeOther(
                                                            e.target.value,
                                                        )
                                                    }
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                    )}
                                </div>

                                <PartnerOrganizationChips errors={errors} />
                            </FormSection>

                            {!isOnCalendar && (
                                <FormSection title="When and where">
                                    <FormField
                                        id="venue"
                                        label="Venue"
                                        error={errors.venue}
                                        helper={
                                            <>
                                                Check the{' '}
                                                <a
                                                    href={calendarRoutes.index().url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="font-medium text-primary-text underline underline-offset-2"
                                                >
                                                    Venue Calendar
                                                    <span className="sr-only">
                                                        {' '}
                                                        (opens in a new tab)
                                                    </span>
                                                </a>{' '}
                                                first to make sure it’s free.
                                                Type the venue the same way
                                                every time so a clash can be
                                                detected.
                                            </>
                                        }
                                    >
                                        {(aria) => (
                                            <div className="relative">
                                                <MapPin
                                                    aria-hidden
                                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                                />
                                                <Input
                                                    id="venue"
                                                    name="venue"
                                                    value={venue}
                                                    onChange={(e) =>
                                                        setVenue(e.target.value)
                                                    }
                                                    placeholder="e.g. NU Lipa Gymnasium"
                                                    className="pl-9"
                                                    {...aria}
                                                />
                                            </div>
                                        )}
                                    </FormField>

                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <FormField
                                            id="activity_date"
                                            label="Date"
                                            error={errors.activity_date}
                                        >
                                            {(aria) => (
                                                <Input
                                                    id="activity_date"
                                                    name="activity_date"
                                                    type="date"
                                                    value={activityDate}
                                                    min={minDate}
                                                    onChange={(e) =>
                                                        setActivityDate(
                                                            e.target.value,
                                                        )
                                                    }
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                        <FormField
                                            id="start_time"
                                            label="Start time"
                                            error={errors.start_time}
                                        >
                                            {(aria) => (
                                                <Input
                                                    id="start_time"
                                                    name="start_time"
                                                    type="time"
                                                    value={startTime}
                                                    onChange={(e) =>
                                                        setStartTime(e.target.value)
                                                    }
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                        <FormField
                                            id="end_time"
                                            label="End time"
                                            error={errors.end_time}
                                        >
                                            {(aria) => (
                                                <Input
                                                    id="end_time"
                                                    name="end_time"
                                                    type="time"
                                                    value={endTime}
                                                    onChange={(e) =>
                                                        setEndTime(e.target.value)
                                                    }
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                    </div>

                                    {/* Term is a global, admin-controlled setting: shown read-only. */}
                                    <div className="grid gap-1.5">
                                        <span
                                            id="term-label"
                                            className="text-sm leading-snug font-medium"
                                        >
                                            Term
                                        </span>
                                        <div
                                            aria-labelledby="term-label"
                                            className="flex items-center justify-between gap-3 rounded-md border bg-muted/40 px-3 py-2 text-sm"
                                        >
                                            <span>{current_term_label}</span>
                                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <Lock aria-hidden className="size-3.5" />
                                                Set by SDAO
                                            </span>
                                        </div>
                                    </div>

                                    {confirmedConflicts.length > 0 && (
                                        <PageNotice
                                            tone="destructive"
                                            urgent
                                            title="Venue conflict. This slot is already booked."
                                        >
                                            {confirmedConflicts.map((c, i) => (
                                                <span key={i} className="block">
                                                    {c.name} ({c.organization}) ·{' '}
                                                    {c.start_time}–{c.end_time}
                                                </span>
                                            ))}
                                        </PageNotice>
                                    )}
                                    {tentativeConflicts.length > 0 &&
                                        confirmedConflicts.length === 0 && (
                                            <PageNotice
                                                tone="warning"
                                                title="Possible conflict. Another pending activity overlaps this slot."
                                            >
                                                {tentativeConflicts.map((c, i) => (
                                                    <span key={i} className="block">
                                                        {c.name} ({c.organization}) ·{' '}
                                                        {c.start_time}–{c.end_time}
                                                    </span>
                                                ))}
                                            </PageNotice>
                                        )}
                                </FormSection>
                            )}

                            <FormSection
                                title="Target SDGs"
                                aside={
                                    <>
                                        Pick at least one ·{' '}
                                        <span className="tabular-nums">
                                            {targetSdg.length} selected
                                        </span>
                                    </>
                                }
                            >
                                <SdgChipGroup
                                    label="Target SDGs, pick one or more"
                                    describedBy={
                                        errors.target_sdg ? 'sdg-error' : undefined
                                    }
                                    options={sdgs}
                                    selected={targetSdg}
                                    onChange={setTargetSdg}
                                    invalid={Boolean(errors.target_sdg)}
                                />
                                {errors.target_sdg && (
                                    <p id="sdg-error" className="text-sm text-destructive">
                                        {errors.target_sdg}
                                    </p>
                                )}
                            </FormSection>

                            <FormSection title="Budget">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="proposed_budget"
                                        label="Proposed budget"
                                        error={errors.proposed_budget}
                                    >
                                        {(aria) => (
                                            <div
                                                className={cn(
                                                    'border-input focus-within:focus-ring flex h-9 items-stretch overflow-hidden rounded-md border shadow-xs',
                                                    aria['aria-invalid'] &&
                                                        'border-destructive',
                                                )}
                                            >
                                                <span
                                                    aria-hidden
                                                    className="flex items-center border-r bg-muted/50 px-3 text-sm text-muted-foreground"
                                                >
                                                    ₱
                                                </span>
                                                <input
                                                    id="proposed_budget"
                                                    name="proposed_budget"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    inputMode="decimal"
                                                    placeholder="0.00"
                                                    className="placeholder:text-muted-foreground min-w-0 flex-1 bg-transparent px-3 text-base outline-none md:text-sm"
                                                    {...aria}
                                                />
                                            </div>
                                        )}
                                    </FormField>

                                    <FormField
                                        id="budget_source"
                                        label="Budget source"
                                        error={errors.budget_source}
                                    >
                                        {(aria) => (
                                            <Select
                                                name="budget_source"
                                                value={budgetSource}
                                                onValueChange={setBudgetSource}
                                            >
                                                <SelectTrigger
                                                    id="budget_source"
                                                    className="w-full"
                                                    {...aria}
                                                >
                                                    <SelectValue placeholder="Select source" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {budgetSources.map((b) => (
                                                        <SelectItem
                                                            key={b.value}
                                                            value={b.value}
                                                        >
                                                            {b.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </FormField>
                                </div>
                            </FormSection>

                            <FormSection
                                title="Attachments"
                                aside="PDF or image, up to 10 MB each"
                            >
                                {attachmentSlots.map((slot) => (
                                    <AttachmentDropZone
                                        key={slot.key}
                                        slot={slot}
                                        label={SLOT_COPY[slot.key]?.label}
                                        helper={SLOT_COPY[slot.key]?.helper}
                                        error={errors[`attachments.${slot.key}`]}
                                    />
                                ))}
                                {errors.activity && (
                                    <p className="text-sm text-destructive">
                                        {errors.activity}
                                    </p>
                                )}
                            </FormSection>

                            <FormFooter status="Your request form is saved as a draft when you continue.">
                                <Button
                                    type="submit"
                                    loading={processing}
                                    loadingText="Continuing…"
                                >
                                    Continue to Narrative
                                    <ArrowRight aria-hidden />
                                </Button>
                            </FormFooter>
                        </FormCard>
                    )}
                </Form>
            </FormShell>
        </>
    );
}

CreateActivityProposal.layout = {
    breadcrumbs: [
        { title: 'Activity Proposals', href: '/activity-proposals' },
        { title: 'New' },
    ],
    columnWidth: '3xl',
};
