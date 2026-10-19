import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import AfterActivityReportController from '@/actions/App/Http/Controllers/AfterActivityReportController';
import { ActivityCard } from '@/components/activity-picker';
import type {
    AttachmentSlotDef,
    ExistingAttachment,
} from '@/components/attachment-slot-field';
import {
    FixFooter,
    FixItemBox,
    FixProvider,
    FixShell,
    FixSection,
    FixStrip,
    FixSummary,
    FlaggedFile,
    LockedField,
    LockedGrid,
    LockedRequirements,
    fileBadges,
    lockedSummary,
    useFix,
} from '@/components/fix-resend';
import {
    AddonInput,
    FocusFirstError,
    FormCard,
    FormField,
    FormFooter,
    FormSection,
    FormStrip,
} from '@/components/form-shell';
import FormSubmitConfirm from '@/components/form-submit-confirm';
import ReportFormSections from '@/components/report-form-sections';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { hasChanged } from '@/lib/fix-resend';
import type { FixProps } from '@/lib/fix-resend';

type DocumentData = { id: number; title: string };

type DetailData = {
    summary: string;
    outcomes: string | null;
    participant_count: number | null;
    activity_chairs: string[] | null;
    prepared_by: string | null;
    event_program: string | null;
    target_participants_percentage: number | null;
    activity: {
        title: string;
        venue: string | null;
        activity_date: string | null;
        start_time: string | null;
        end_time: string | null;
    } | null;
} | null;

type Props = {
    document: DocumentData;
    detail: DetailData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    fix: FixProps;
};

export default function EditReport({ fix, ...props }: Props) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [{ title: 'Reports' }, { title: 'Fix and Resend' }],
            trailParent: { title: fix.back.title, href: fix.back.href },
        });
    }, [fix.back.title, fix.back.href]);

    return (
        <FixProvider fix={fix}>
            <Head title="Fix and Resend" />
            <ReportFix {...props} />
        </FixProvider>
    );
}

function ReportFix({
    document,
    detail,
    attachmentSlots,
    attachments,
}: Omit<Props, 'fix'>) {
    const { auth } = usePage().props;
    const { fix, isFlagged } = useFix();
    const formId = `edit-report-form-${document.id}`;
    const draft = fix.draft ?? {};
    const flaggedKeys = fix.items.map((item) => item.key);
    const [files, setFiles] = useState<Record<string, File[]>>({});

    const baseSummary = {
        summary: detail?.summary ?? '',
        event_program: detail?.event_program ?? '',
        activity_chairs: detail?.activity_chairs?.length
            ? detail.activity_chairs
            : [''],
        prepared_by: detail?.prepared_by ?? '',
    };
    const baseEvaluation = {
        outcomes: detail?.outcomes ?? '',
        target_participants_percentage: String(
            detail?.target_participants_percentage ?? '',
        ),
        participant_count: String(detail?.participant_count ?? ''),
    };
    const [summary, setSummary] = useState({
        ...baseSummary,
        ...(draft.summary_program as Partial<typeof baseSummary>),
    });
    const [evaluation, setEvaluation] = useState({
        ...baseEvaluation,
        ...(draft.evaluation as Partial<typeof baseEvaluation>),
    });

    const flags = {
        flaggedSections: flaggedKeys,
        flaggedComment: fix.comment,
        flaggedSectionComments: Object.fromEntries(
            fix.items.map((item) => [item.key, item.comment ?? '']),
        ),
    };
    const flaggedSlots = attachmentSlots.filter((slot) =>
        flaggedKeys.includes(slot.key),
    );
    const lockedSlots = attachmentSlots.filter(
        (slot) => !flaggedKeys.includes(slot.key),
    );

    return (
        <FixShell fix={fix}>
            <Form
                {...AfterActivityReportController.update.form({
                    document: document.id,
                })}
                id={formId}
            >
                {({ processing, errors }) => (
                    <FormCard>
                        <FocusFirstError errors={errors} />
                        {fix.active ? (
                            <>
                                <FixStrip
                                    organization={auth.organization?.name}
                                />
                                <FixSummary />
                            </>
                        ) : (
                            <>
                                <FormStrip
                                    left={
                                        auth.organization ? (
                                            <>
                                                Reporting for{' '}
                                                <strong className="font-semibold text-foreground">
                                                    {auth.organization.name}
                                                </strong>
                                            </>
                                        ) : (
                                            document.title
                                        )
                                    }
                                    right={auth.organization?.school?.name}
                                />
                                <FixSummary />
                            </>
                        )}
                        {(errors.locked || errors.flagged) && (
                            <p
                                role="alert"
                                className="border-b bg-destructive/10 px-5 py-3 text-sm text-destructive-foreground sm:px-6"
                            >
                                {errors.locked} {errors.flagged}
                            </p>
                        )}

                        {detail?.activity && (
                            <FormSection title="Which activity?">
                                <ActivityCard
                                    showDate
                                    activity={{
                                        id: String(document.id),
                                        title: detail.activity.title,
                                        date:
                                            detail.activity.activity_date ?? '',
                                        start_time: detail.activity.start_time,
                                        end_time: detail.activity.end_time,
                                        venue: detail.activity.venue,
                                    }}
                                />
                                <p className="mt-2 text-xs text-muted-foreground">
                                    The activity can’t be changed on a returned
                                    report.
                                </p>
                            </FormSection>
                        )}

                        {fix.active ? (
                            <>
                                <FixSection
                                    title="Summary and program"
                                    itemKeys={['summary_program']}
                                >
                                    <FixItemBox
                                        sectionKey="summary_program"
                                        changed={hasChanged(
                                            baseSummary,
                                            summary,
                                        )}
                                        locked={
                                            <LockedGrid columns={1}>
                                                <LockedField
                                                    label="Summary"
                                                    value={baseSummary.summary}
                                                />
                                                <LockedField
                                                    label="Program"
                                                    value={
                                                        baseSummary.event_program
                                                    }
                                                />
                                                <LockedField
                                                    label="Activity chairs"
                                                    value={baseSummary.activity_chairs
                                                        .filter(Boolean)
                                                        .join(', ')}
                                                />
                                                <LockedField
                                                    label="Prepared by"
                                                    value={
                                                        baseSummary.prepared_by
                                                    }
                                                />
                                            </LockedGrid>
                                        }
                                    >
                                        <FormField
                                            id="summary" required
                                            label="Summary"
                                            error={errors.summary}
                                        >
                                            {(aria) => (
                                                <Textarea
                                                    id="summary"
                                                    name="summary"
                                                    rows={4}
                                                    value={summary.summary}
                                                    onChange={(event) =>
                                                        setSummary((prev) => ({
                                                            ...prev,
                                                            summary:
                                                                event.target
                                                                    .value,
                                                        }))
                                                    }
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                        <FormField
                                            id="event_program" required
                                            label="Program"
                                            error={errors.event_program}
                                        >
                                            {(aria) => (
                                                <Textarea
                                                    id="event_program"
                                                    name="event_program"
                                                    rows={4}
                                                    value={
                                                        summary.event_program
                                                    }
                                                    onChange={(event) =>
                                                        setSummary((prev) => ({
                                                            ...prev,
                                                            event_program:
                                                                event.target
                                                                    .value,
                                                        }))
                                                    }
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                        <fieldset className="grid gap-2">
                                            <legend className="mb-1.5 text-sm leading-snug font-medium">
                                                Activity chairs
                                            </legend>
                                            {summary.activity_chairs.map(
                                                (chair, index) => {
                                                    const chairError =
                                                        errors[
                                                            `activity_chairs.${index}`
                                                        ] ??
                                                        (index === 0
                                                            ? errors.activity_chairs
                                                            : undefined);

                                                    return (
                                                        <div
                                                            key={index}
                                                            className="grid gap-1"
                                                        >
                                                            <div className="flex items-center gap-2">
                                                                <Input
                                                                    id={`activity_chairs_${index}`}
                                                                    name={`activity_chairs[${index}]`}
                                                                    value={
                                                                        chair
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setSummary(
                                                                            (
                                                                                prev,
                                                                            ) => ({
                                                                                ...prev,
                                                                                activity_chairs:
                                                                                    prev.activity_chairs.map(
                                                                                        (
                                                                                            current,
                                                                                            i,
                                                                                        ) =>
                                                                                            i ===
                                                                                            index
                                                                                                ? event
                                                                                                      .target
                                                                                                      .value
                                                                                                : current,
                                                                                    ),
                                                                            }),
                                                                        )
                                                                    }
                                                                    placeholder="Full name"
                                                                    aria-label={`Activity chair ${index + 1}`}
                                                                    aria-invalid={
                                                                        chairError
                                                                            ? true
                                                                            : undefined
                                                                    }
                                                                />
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="icon"
                                                                    disabled={
                                                                        summary
                                                                            .activity_chairs
                                                                            .length ===
                                                                        1
                                                                    }
                                                                    aria-label={`Remove activity chair ${index + 1}`}
                                                                    onClick={() =>
                                                                        setSummary(
                                                                            (
                                                                                prev,
                                                                            ) => ({
                                                                                ...prev,
                                                                                activity_chairs:
                                                                                    prev.activity_chairs.filter(
                                                                                        (
                                                                                            _,
                                                                                            i,
                                                                                        ) =>
                                                                                            i !==
                                                                                            index,
                                                                                    ),
                                                                            }),
                                                                        )
                                                                    }
                                                                >
                                                                    <X
                                                                        aria-hidden
                                                                    />
                                                                </Button>
                                                            </div>
                                                            {chairError && (
                                                                <p className="text-sm text-destructive">
                                                                    {chairError}
                                                                </p>
                                                            )}
                                                        </div>
                                                    );
                                                },
                                            )}
                                            <Button
                                                type="button"
                                                variant="link"
                                                size="sm"
                                                className="h-auto w-fit p-0"
                                                onClick={() =>
                                                    setSummary((prev) => ({
                                                        ...prev,
                                                        activity_chairs: [
                                                            ...prev.activity_chairs,
                                                            '',
                                                        ],
                                                    }))
                                                }
                                            >
                                                <Plus aria-hidden />
                                                Add another chair
                                            </Button>
                                        </fieldset>
                                        <FormField
                                            id="prepared_by" required
                                            label="Prepared by"
                                            error={errors.prepared_by}
                                        >
                                            {(aria) => (
                                                <Input
                                                    id="prepared_by"
                                                    name="prepared_by"
                                                    value={summary.prepared_by}
                                                    onChange={(event) =>
                                                        setSummary((prev) => ({
                                                            ...prev,
                                                            prepared_by:
                                                                event.target
                                                                    .value,
                                                        }))
                                                    }
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                    </FixItemBox>
                                </FixSection>

                                <FixSection
                                    title="Evaluation"
                                    itemKeys={['evaluation']}
                                >
                                    <FixItemBox
                                        sectionKey="evaluation"
                                        changed={hasChanged(
                                            baseEvaluation,
                                            evaluation,
                                        )}
                                        locked={
                                            <LockedGrid>
                                                <LockedField
                                                    label="Outcomes"
                                                    value={
                                                        baseEvaluation.outcomes
                                                    }
                                                />
                                                <LockedField
                                                    label="Target participants reached"
                                                    value={
                                                        baseEvaluation.target_participants_percentage &&
                                                        `${baseEvaluation.target_participants_percentage}%`
                                                    }
                                                />
                                                <LockedField
                                                    label="Number of participants"
                                                    value={
                                                        baseEvaluation.participant_count
                                                    }
                                                />
                                            </LockedGrid>
                                        }
                                    >
                                        <FormField
                                            id="outcomes"
                                            label="Outcomes"
                                            optional
                                            error={errors.outcomes}
                                        >
                                            {(aria) => (
                                                <Textarea
                                                    id="outcomes"
                                                    name="outcomes"
                                                    rows={4}
                                                    value={evaluation.outcomes}
                                                    onChange={(event) =>
                                                        setEvaluation(
                                                            (prev) => ({
                                                                ...prev,
                                                                outcomes:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <FormField
                                                id="target_participants_percentage" required
                                                label="Target participants reached"
                                                error={
                                                    errors.target_participants_percentage
                                                }
                                            >
                                                {(aria) => (
                                                    <AddonInput
                                                        id="target_participants_percentage"
                                                        name="target_participants_percentage"
                                                        type="number"
                                                        min={0}
                                                        max={100}
                                                        inputMode="numeric"
                                                        value={
                                                            evaluation.target_participants_percentage
                                                        }
                                                        onChange={(event) =>
                                                            setEvaluation(
                                                                (prev) => ({
                                                                    ...prev,
                                                                    target_participants_percentage:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                        suffix="%"
                                                        invalid={
                                                            aria['aria-invalid']
                                                        }
                                                        aria-describedby={
                                                            aria[
                                                                'aria-describedby'
                                                            ]
                                                        }
                                                    />
                                                )}
                                            </FormField>
                                            <FormField
                                                id="participant_count"
                                                label="Number of participants"
                                                optional
                                                error={errors.participant_count}
                                            >
                                                {(aria) => (
                                                    <AddonInput
                                                        id="participant_count"
                                                        name="participant_count"
                                                        type="number"
                                                        min={0}
                                                        inputMode="numeric"
                                                        value={
                                                            evaluation.participant_count
                                                        }
                                                        onChange={(event) =>
                                                            setEvaluation(
                                                                (prev) => ({
                                                                    ...prev,
                                                                    participant_count:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                        suffix="people"
                                                        invalid={
                                                            aria['aria-invalid']
                                                        }
                                                        aria-describedby={
                                                            aria[
                                                                'aria-describedby'
                                                            ]
                                                        }
                                                    />
                                                )}
                                            </FormField>
                                        </div>
                                    </FixItemBox>
                                </FixSection>

                                <FixSection
                                    title="Attachments"
                                    itemKeys={flaggedSlots.map(
                                        (slot) => slot.key,
                                    )}
                                    lockedLabel={lockedSummary(
                                        attachmentSlots,
                                        flaggedKeys,
                                    )}
                                >
                                    {flaggedSlots.map((slot) => (
                                        <FixItemBox
                                            key={slot.key}
                                            sectionKey={slot.key}
                                            changed={
                                                (files[slot.key]?.length ?? 0) >
                                                0
                                            }
                                            badges={fileBadges(
                                                (files[slot.key]?.length ?? 0) >
                                                    0,
                                            )}
                                            locked={null}
                                        >
                                            <FlaggedFile
                                                slot={slot}
                                                existing={
                                                    attachments[slot.key] ?? []
                                                }
                                                files={files[slot.key] ?? []}
                                                onFilesChange={(list) =>
                                                    setFiles((prev) => ({
                                                        ...prev,
                                                        [slot.key]: list,
                                                    }))
                                                }
                                                error={
                                                    errors[
                                                        `attachments.${slot.key}`
                                                    ]
                                                }
                                            />
                                        </FixItemBox>
                                    ))}
                                    <LockedRequirements
                                        slots={lockedSlots}
                                        existing={attachments}
                                    />
                                </FixSection>
                            </>
                        ) : (
                            <ReportFormSections
                                errors={errors}
                                defaults={detail ?? undefined}
                                attachmentSlots={attachmentSlots}
                                attachments={attachments}
                                flags={flags}
                            />
                        )}

                        {fix.active ? (
                            <FixFooter
                                formId={formId}
                                processing={processing}
                                collect={() => ({
                                    ...(isFlagged('summary_program')
                                        ? { summary_program: summary }
                                        : {}),
                                    ...(isFlagged('evaluation')
                                        ? { evaluation }
                                        : {}),
                                })}
                                title="Resend this report?"
                                description={`It goes back to ${fix.role}, who asked for these changes.`}
                            />
                        ) : (
                            <FormFooter
                                status={`${fix.role} sees it again as soon as you resend.`}
                            >
                                <FormSubmitConfirm
                                    formId={formId}
                                    processing={processing}
                                    title="Resend this report?"
                                    description={`It goes back to ${fix.role}, who returned it.`}
                                    confirmLabel={fix.resendLabel}
                                >
                                    {fix.resendLabel}
                                </FormSubmitConfirm>
                            </FormFooter>
                        )}
                    </FormCard>
                )}
            </Form>
        </FixShell>
    );
}

EditReport.layout = {
    breadcrumbs: [{ title: 'Reports' }, { title: 'Fix and Resend' }],
    columnWidth: '3xl',
};
