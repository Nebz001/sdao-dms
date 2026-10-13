import { Head, router, usePage } from '@inertiajs/react';
import { CalendarDays, Lock } from 'lucide-react';
import { useRef, useState } from 'react';
import ActivityCalendarEditor, {
    clockTime,
    newUid,
    toActivityFields,
} from '@/components/activity-calendar-editor';
import type {
    ActivityFields,
    EditorRow,
    SdgOption,
} from '@/components/activity-calendar-editor';
import BlockedState from '@/components/blocked-state';
import ConfirmDialog from '@/components/confirm-dialog';
import type { ConfirmActions } from '@/components/confirm-dialog';
import {
    FormCard,
    FormFooter,
    FormShell,
    FormStrip,
} from '@/components/form-shell';
import PageNotice from '@/components/page-notice';
import { NotAnOfficerBlocked } from '@/components/student-blocked';
import { Button } from '@/components/ui/button';
import { notify } from '@/lib/toast';
import { todayDateString } from '@/lib/utils';
import * as activityCalendars from '@/routes/activity-calendars';

type Membership = {
    id: number;
    position: string;
    position_label: string;
    organization: { id: number; name: string };
};

type ExistingDocument = {
    id: number;
    status: string;
    href: string;
};

type Eligibility = {
    status: 'eligible' | 'already_filed';
    message: string | null;
    existingDocument: ExistingDocument | null;
};

type Props = {
    membership: Membership | null;
    current_term_label: string;
    eligibility: Eligibility | null;
    sdgs: SdgOption[];
};

const FORM_ID = 'activity-calendar-form';

/** The draft lives in this browser only: the server has no record of a calendar that was never submitted. */
function draftKey(membership: Membership | null): string {
    return `activity-calendar-draft:${membership?.organization.id ?? 'none'}`;
}

function readDraft(key: string): EditorRow[] {
    try {
        const raw = window.localStorage.getItem(key);
        const parsed = raw
            ? (JSON.parse(raw) as Partial<ActivityFields>[])
            : [];

        return parsed.map((row) => ({
            name: row.name ?? '',
            venue: row.venue ?? '',
            activity_date: row.activity_date ?? '',
            start_time: clockTime(row.start_time),
            end_time: clockTime(row.end_time),
            description: row.description ?? '',
            sdg: row.sdg ?? [],
            participant_program_assigned:
                row.participant_program_assigned ?? '',
            budget: row.budget ?? '',
            uid: newUid(),
            position: null,
        }));
    } catch {
        return [];
    }
}

export default function CreateActivityCalendar({
    membership,
    current_term_label,
    eligibility,
    sdgs,
}: Props) {
    const { currentPeriod } = usePage().props as {
        currentPeriod?: { academic_year?: string };
    };
    const key = draftKey(membership);
    const [rows, setRows] = useState<EditorRow[]>(() =>
        typeof window === 'undefined' ? [] : readDraft(key),
    );
    const [restored] = useState(rows.length > 0);
    const confirmActions = useRef<ConfirmActions | null>(null);
    const [pending, setPending] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const minDate = todayDateString();

    function saveDraft() {
        try {
            window.localStorage.setItem(
                key,
                JSON.stringify(rows.map(toActivityFields)),
            );
            notify.success({
                title: 'Draft saved',
                message:
                    'Your list is saved in this browser. Come back to this page to pick it up.',
            });
        } catch {
            notify.error({
                title: 'Couldn’t save the draft',
                message:
                    'This browser blocked saving. Submit when you’re ready instead.',
            });
        }
    }

    function submit() {
        setProcessing(true);
        setErrors({});

        router.post(
            '/activity-calendars',
            { activities: rows.map(toActivityFields) },
            {
                onSuccess: () => {
                    try {
                        window.localStorage.removeItem(key);
                    } catch {
                        // Nothing to clean up if storage is blocked.
                    }
                },
                onError: (errs) => setErrors(errs as Record<string, string>),
                onFinish: () => {
                    setProcessing(false);
                    confirmActions.current?.close();
                },
            },
        );
    }

    if (!membership || eligibility?.status !== 'eligible') {
        return (
            <>
                <Head title="Submit Activity Calendar" />
                {!membership ? (
                    <NotAnOfficerBlocked action="submit an activity calendar" />
                ) : (
                    <BlockedState
                        icon={CalendarDays}
                        title="Activity calendar already filed"
                        body={
                            eligibility?.message ?? (
                                <>
                                    <strong>
                                        {membership.organization.name}
                                    </strong>{' '}
                                    already has an activity calendar for{' '}
                                    <strong>{current_term_label}</strong>.
                                </>
                            )
                        }
                        primary={
                            eligibility?.existingDocument
                                ? {
                                      label:
                                          eligibility.existingDocument
                                              .status === 'returned'
                                              ? 'Edit calendar'
                                              : 'View calendar',
                                      href: eligibility.existingDocument.href,
                                  }
                                : {
                                      label: 'My Activity Calendars',
                                      href: activityCalendars.index(),
                                  }
                        }
                    />
                )}
            </>
        );
    }

    const period = [current_term_label, currentPeriod?.academic_year]
        .filter(Boolean)
        .join(', ');
    const noActivities = rows.length === 0;

    return (
        <>
            <Head title="Submit Activity Calendar" />

            <FormShell
                title="Activity Calendar"
                subtitle="List the activities your organization plans for this term. Add them one at a time."
            >
                <form
                    id={FORM_ID}
                    onSubmit={(e) => e.preventDefault()}
                    noValidate
                >
                    <FormCard>
                        <FormStrip
                            left={
                                <>
                                    Submitting for{' '}
                                    <strong className="font-semibold text-foreground">
                                        {membership.organization.name}
                                    </strong>{' '}
                                    as {membership.position_label}
                                </>
                            }
                            right={
                                <span className="flex items-center gap-1.5">
                                    <Lock aria-hidden className="size-3.5" />
                                    {period} · Set by SDAO
                                </span>
                            }
                        />

                        {restored && (
                            <div className="border-b px-5 py-3 sm:px-6">
                                <PageNotice tone="info">
                                    We restored the list you saved in this
                                    browser.
                                </PageNotice>
                            </div>
                        )}

                        {(errors.period || errors.activities) && (
                            <div className="space-y-2 border-b px-5 py-3 sm:px-6">
                                {errors.period && (
                                    <PageNotice
                                        tone="destructive"
                                        urgent
                                        title={errors.period}
                                    />
                                )}
                                {errors.activities && (
                                    <PageNotice
                                        tone="destructive"
                                        urgent
                                        title={errors.activities}
                                    />
                                )}
                            </div>
                        )}

                        <ActivityCalendarEditor
                            idPrefix="calendar"
                            rows={rows}
                            onRowsChange={(update) => {
                                // Errors point at rows by position; any change makes them stale.
                                setErrors({});
                                setRows(update);
                            }}
                            sdgs={sdgs}
                            errors={errors}
                            minDate={minDate}
                            checkConflicts
                            onPendingChange={setPending}
                        />

                        <FormFooter status="You can add more activities anytime before you submit.">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={noActivities || processing}
                                onClick={saveDraft}
                            >
                                Save draft
                            </Button>
                            <Button
                                type="button"
                                disabled={noActivities || processing}
                                aria-describedby={
                                    noActivities
                                        ? 'calendar-submit-hint'
                                        : undefined
                                }
                                onClick={() => setConfirmOpen(true)}
                            >
                                Submit for Review
                            </Button>
                        </FormFooter>
                        {noActivities && (
                            <p id="calendar-submit-hint" className="sr-only">
                                Add at least one activity to submit.
                            </p>
                        )}
                    </FormCard>
                </form>
            </FormShell>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title="Submit this calendar for review?"
                description={`SDAO reviews all ${rows.length} ${rows.length === 1 ? 'activity' : 'activities'} together. You can’t change the calendar unless it’s returned to you.`}
                notice={
                    pending ? (
                        <PageNotice
                            tone="warning"
                            title="The form isn’t added to the list yet."
                        >
                            What you typed there won’t be submitted. Cancel,
                            then choose “Add to list” or “Save changes” first.
                        </PageNotice>
                    ) : undefined
                }
                confirmLabel="Submit for Review"
                onConfirm={(actions) => {
                    confirmActions.current = actions;
                    submit();
                }}
            />
        </>
    );
}

CreateActivityCalendar.layout = {
    breadcrumbs: [{ title: 'Activity Calendars' }, { title: 'New' }],
    columnWidth: '3xl',
};
