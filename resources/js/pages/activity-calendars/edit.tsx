import { Head, router, setLayoutProps } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import ActivityCalendarEditor, {
    clockTime,
    newUid,
    toActivityFields,
} from '@/components/activity-calendar-editor';
import type {
    ActivityFields,
    EditorRow,
    RowAccess,
    SdgOption,
} from '@/components/activity-calendar-editor';
import {
    FixFooter,
    FixProvider,
    FixShell,
    FixStrip,
    FixSummary,
    useFix,
} from '@/components/fix-resend';
import { FormCard, FormFooter, FormStrip } from '@/components/form-shell';
import FormSubmitConfirm from '@/components/form-submit-confirm';
import { ToneBadge } from '@/components/status-badge';
import { hasChanged } from '@/lib/fix-resend';
import type { FixProps } from '@/lib/fix-resend';
import { todayDateString } from '@/lib/utils';

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

function toFields(activity: ActivityData): ActivityFields {
    return {
        name: activity.name,
        venue: activity.venue,
        activity_date: activity.activity_date,
        // The H:i rule is strict; a stored time can arrive as H:i:s.
        start_time: clockTime(activity.start_time),
        end_time: clockTime(activity.end_time),
        description: activity.description ?? '',
        sdg: activity.sdg,
        participant_program_assigned:
            activity.participant_program_assigned ?? '',
        budget: activity.budget ?? '',
    };
}

const flagKey = (position: number | null) => `activity_${position}`;

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

/** Tells the progress summary and footer whether each flagged activity has changed yet. */
function FlaggedRowReporter({
    position,
    changed,
}: {
    position: number | null;
    changed: boolean;
}) {
    const { report } = useFix();

    useEffect(() => {
        report(flagKey(position), changed);
    }, [position, changed, report]);

    return null;
}

function CalendarFix({ document, calendar, sdgs }: Omit<Props, 'fix'>) {
    const { fix, isFlagged } = useFix();
    // `position` is the row's place in the stored calendar: the number in its
    // `activity_{n}` flag, and the key the server reads a flagged row by. The
    // list below is sorted for display only, so neither moves.
    const baseRows = (calendar?.activities ?? []).map(toFields);
    const [rows, setRows] = useState<EditorRow[]>(() =>
        baseRows.map((fields, position) => ({
            ...fields,
            ...((fix.draft?.[flagKey(position)] as
                | Partial<ActivityFields>
                | undefined) ?? {}),
            uid: newUid(),
            position,
        })),
    );
    const [pending, setPending] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const minDate = todayDateString();
    const formId = `edit-calendar-form-${document.id}`;

    function submit(event?: React.FormEvent<HTMLFormElement>) {
        event?.preventDefault();
        setProcessing(true);
        setErrors({});

        // On a Fix and Resend only the flagged activities are sent, by their
        // position; the server keeps every other row exactly as it was.
        // Term is frozen at original submission, never resent.
        const payload = fix.active
            ? Object.fromEntries(
                  rows
                      .filter(
                          (row) =>
                              row.position !== null &&
                              isFlagged(flagKey(row.position)),
                      )
                      .map((row) => [row.position, toActivityFields(row)]),
              )
            : rows.map(toActivityFields);

        router.put(
            `/activity-calendars/${document.id}`,
            { activities: payload },
            {
                onError: (errs) => setErrors(errs as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    }

    function rowAccess(row: EditorRow): RowAccess {
        if (!fix.active) {
            return { editable: true };
        }

        const item = fix.items.find(
            (candidate) => candidate.key === flagKey(row.position),
        );

        if (!item) {
            return { editable: false };
        }

        const base = row.position !== null ? baseRows[row.position] : null;
        const changed = base ? hasChanged(base, toActivityFields(row)) : false;

        return {
            editable: true,
            anchorId: `fix-item-${item.key}`,
            badge: changed ? (
                <ToneBadge tone="success">Changed</ToneBadge>
            ) : (
                <ToneBadge tone="warning">Needs a change</ToneBadge>
            ),
            note: item.comment ? (
                <p className="flex items-start gap-2 rounded-md border border-warning/40 bg-warning/5 px-3 py-2 text-sm text-warning-foreground">
                    <MessageCircle
                        aria-hidden
                        className="mt-0.5 size-4 shrink-0"
                    />
                    <span>
                        <strong className="font-semibold">{fix.role}:</strong>{' '}
                        {item.comment}
                    </span>
                </p>
            ) : undefined,
        };
    }

    const pendingNote = pending
        ? ' The form above has changes you haven’t saved to the list, and they won’t be sent.'
        : '';

    return (
        <FixShell fix={fix}>
            <form onSubmit={submit} id={formId} noValidate>
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
                                right={`${calendar?.term_label ?? ''} (set at original submission, can’t be changed)`}
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

                    {fix.active &&
                        rows.map((row) =>
                            row.position !== null &&
                            isFlagged(flagKey(row.position)) ? (
                                <FlaggedRowReporter
                                    key={row.uid}
                                    position={row.position}
                                    changed={hasChanged(
                                        baseRows[row.position],
                                        toActivityFields(row),
                                    )}
                                />
                            ) : null,
                        )}

                    <ActivityCalendarEditor
                        idPrefix="calendar"
                        rows={rows}
                        onRowsChange={setRows}
                        sdgs={sdgs}
                        errors={errors}
                        minDate={minDate}
                        rowAccess={rowAccess}
                        canAddAndDelete={!fix.active}
                        onPendingChange={setPending}
                    />

                    {fix.active ? (
                        <FixFooter
                            formId={formId}
                            processing={processing}
                            collect={() =>
                                Object.fromEntries(
                                    rows
                                        .filter(
                                            (row) =>
                                                row.position !== null &&
                                                isFlagged(
                                                    flagKey(row.position),
                                                ),
                                        )
                                        .map((row) => [
                                            flagKey(row.position),
                                            toActivityFields(row),
                                        ]),
                                )
                            }
                            title="Resend this calendar?"
                            description={`It goes back to ${fix.role}, who asked for these changes.${pendingNote}`}
                        />
                    ) : (
                        <FormFooter
                            status={`${fix.role} sees it again as soon as you resend.`}
                        >
                            <FormSubmitConfirm
                                formId={formId}
                                processing={processing}
                                title="Resend this calendar?"
                                description={`It goes back to ${fix.role}, who returned it.${pendingNote}`}
                                confirmLabel={fix.resendLabel}
                                triggerDisabled={rows.length === 0}
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
