import { Form, Head, router, usePage } from '@inertiajs/react';
import RegistrationReviewController from '@/actions/App/Http/Controllers/RegistrationReviewController';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import AttachmentsCard from '@/components/attachments-card';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import SectionFlagFields from '@/components/section-flag-fields';
import type {SectionFlagDef} from '@/components/section-flag-fields';
import { StatusBadge, statusBorderClass } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewRegistrations from '@/routes/review/registrations';

type Organization = { id: number; name: string; college: string | null; program: string | null };

type DocumentData = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
    organization: Organization;
};

type DetailData = {
    organization_type: string;
    organization_type_label: string;
    purpose_of_organization: string;
    contact_person: string;
    contact_no: string;
    email_address: string;
    date_organized: string;
    adviser: { name: string } | null;
} | null;

type TransitionEntry = {
    id: number;
    action: string;
    from_status: string | null;
    to_status: string;
    step_position: number | null;
    comment: string | null;
    flagged_sections: string[] | null;
    actor: { name: string } | null;
    created_at: string;
};

type StepApproval = { user_id: number; name: string };

type Props = {
    document: DocumentData;
    detail: DetailData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    history: TransitionEntry[];
    flaggedSectionLabels: Record<string, string>;
    sectionFlags: SectionFlagDef[];
    currentStepApprovals: StepApproval[];
    hasApproved: boolean;
    adviserAvailable: boolean;
};

function actionLabel(action: string): string {
    return action.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function ReviewRegistrationShow({
    document,
    detail,
    attachmentSlots,
    attachments,
    history,
    flaggedSectionLabels,
    sectionFlags,
    currentStepApprovals,
    hasApproved,
    adviserAvailable,
}: Props) {
    useDocumentUpdates(['document', 'detail', 'attachments', 'history', 'currentStepApprovals', 'hasApproved', 'adviserAvailable']);

    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const isInReview = document.status === 'in_review';

    function handleApprove() {
        router.post(reviewRegistrations.approve.url(document.id));
    }

    return (
        <>
            <Head title={`Review: ${document.title}`} />

            <div className="max-w-3xl space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">{document.title}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {document.organization.name}
                        </p>
                    </div>
                    <StatusBadge status={document.status} />
                </div>

                {/* Dual-SDAO quorum state */}
                {isInReview && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Quorum Status</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {currentStepApprovals.length === 0 ? (
                                <p className="text-muted-foreground">
                                    Neither SDAO member has approved yet.
                                </p>
                            ) : (
                                <p>
                                    Approved by:{' '}
                                    {currentStepApprovals.map((a) => a.name).join(', ')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Detail card */}
                {detail && (
                    <Card className={`border-l-4 ${statusBorderClass(document.status)}`}>
                        <CardHeader>
                            <CardTitle className="text-base">Registration Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            {/* Field-presence parity (Phase 2 item 7 slice 2) */}
                            <Row label="Organization Name" value={document.organization.name} />
                            <Row label="College" value={document.organization.college ?? '—'} />
                            {document.organization.program && (
                                <Row label="Program" value={document.organization.program} />
                            )}
                            <Row label="Type of Organization" value={detail.organization_type_label} />
                            <Row label="Contact Person" value={detail.contact_person} />
                            <Row label="Contact No." value={detail.contact_no} />
                            <Row label="Email Address" value={detail.email_address} />
                            <Row label="Date Organized" value={detail.date_organized} />
                            {detail.adviser && (
                                <Row label="Adviser" value={detail.adviser.name} />
                            )}
                            <div className="grid gap-1">
                                <span className="font-medium text-muted-foreground">Purpose of Organization</span>
                                <p className="whitespace-pre-wrap">{detail.purpose_of_organization}</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <AttachmentsCard slots={attachmentSlots} files={attachments} />

                {/* Review actions */}
                {isInReview && !hasApproved && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Review Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Approve — blocked when the chosen adviser is no longer
                                available (Phase 2 item 5 race-condition guard) */}
                            {!adviserAvailable || errors.approve ? (
                                <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                    ⛔{' '}
                                    {errors.approve ??
                                        'Cannot approve: the chosen adviser is now assigned to a different organization.'}{' '}
                                    Return the document so the student can pick a different adviser.
                                </div>
                            ) : (
                                <ConfirmDialog
                                    trigger={<Button className="w-full sm:w-auto">Approve</Button>}
                                    title="Approve this registration?"
                                    description="This action is irreversible once the SDAO quorum is met — the organization becomes real, the adviser is bound, and the founding student is locked to this organization going forward."
                                    confirmLabel="Confirm Approval"
                                    onConfirm={handleApprove}
                                />
                            )}

                            {/* Return for revision */}
                            <Form
                                {...RegistrationReviewController.return.form({ document: document.id })}
                                className="space-y-2 border-t pt-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <p className="text-sm font-medium">Return for Revision</p>
                                        <Textarea
                                            name="comment"
                                            placeholder="Explain what the student needs to revise…"
                                            rows={3}
                                            required
                                        />
                                        <InputError message={errors.comment} />
                                        <SectionFlagFields sections={sectionFlags} />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Return
                                        </Button>
                                    </>
                                )}
                            </Form>

                            {/* Reject */}
                            <Form
                                {...RegistrationReviewController.reject.form({ document: document.id })}
                                id={`reject-form-${document.id}`}
                                className="space-y-2 border-t pt-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <p className="text-sm font-medium text-destructive">
                                            Reject (permanent)
                                        </p>
                                        <Textarea
                                            name="comment"
                                            placeholder="Reason for rejection…"
                                            rows={3}
                                            required
                                        />
                                        <InputError message={errors.comment} />
                                        <ConfirmDialog
                                            trigger={
                                                <Button type="button" variant="destructive" disabled={processing}>
                                                    Reject
                                                </Button>
                                            }
                                            title="Reject this registration?"
                                            description="This is permanent — the student cannot revive this document. They must file a brand-new registration."
                                            confirmLabel="Reject"
                                            confirmVariant="destructive"
                                            confirmForm={`reject-form-${document.id}`}
                                            confirmDisabled={processing}
                                        />
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {isInReview && hasApproved && (
                    <p className="text-sm text-muted-foreground">
                        You have already approved this step. Waiting for the other SDAO member.
                    </p>
                )}

                {/* History */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Transition History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="relative border-l border-border pl-4">
                            {history.map((entry) => (
                                <li key={entry.id} className="mb-4 ml-2">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">{actionLabel(entry.action)}</span>
                                        {entry.actor && (
                                            <span className="text-sm text-muted-foreground">
                                                — {entry.actor.name}
                                            </span>
                                        )}
                                    </div>
                                    {entry.comment && (
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            "{entry.comment}"
                                        </p>
                                    )}
                                    {entry.flagged_sections && entry.flagged_sections.length > 0 && (
                                        <p className="mt-1 text-xs text-destructive">
                                            Flagged: {entry.flagged_sections.map((key) => flaggedSectionLabels[key] ?? key).join(', ')}
                                        </p>
                                    )}
                                    <time className="text-xs text-muted-foreground">
                                        {new Date(entry.created_at).toLocaleString()}
                                    </time>
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-3 gap-2">
            <span className="font-medium text-muted-foreground">{label}</span>
            <span className="col-span-2">{value}</span>
        </div>
    );
}

ReviewRegistrationShow.layout = {
    breadcrumbs: [
        { title: 'Review' },
        { title: 'Registrations', href: reviewRegistrations.index() },
        { title: 'Review' },
    ],
};
