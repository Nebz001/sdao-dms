import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { Ban, FilePlus2, Hourglass, UserCog, UserPlus } from 'lucide-react';
import ApproverDashboard, { ApproverGreeting } from '@/components/approver-dashboard';
import type {
    ApproverHeader,
    ApproverNeedsReview,
    ApproverSummary,
    ComingUpEvent,
    InProgressRow,
    RecentDecision,
} from '@/components/approver-dashboard';
import ApproverDashboardSkeleton from '@/components/approver-dashboard-skeleton';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import type { RequirementsData } from '@/components/requirements-checklist';
import StudentDashboard from '@/components/student-dashboard';
import type {
    NeedsActionData,
    QuickSubmitTile,
    StudentDashboardMeta,
    StudentKpis,
    TrackerData,
    UpcomingData,
} from '@/components/student-dashboard';
import StudentDashboardSkeleton from '@/components/student-dashboard-skeleton';
import type { SubmissionMonth } from '@/components/submissions-chart';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import * as calendar from '@/routes/calendar';
import * as organizationsJoin from '@/routes/organizations/join';
import * as registrations from '@/routes/registrations';

type PendingJoinRequest = { organizationName: string };
type Props = {
    studentDashboard: StudentDashboardMeta | null;
    pendingJoinRequest: PendingJoinRequest | null;
    approverDashboard: ApproverHeader | null;
    studentKpis?: StudentKpis;
    studentNeedsAction?: NeedsActionData;
    studentTracker?: TrackerData;
    studentRequirements?: RequirementsData;
    studentQuickSubmit?: QuickSubmitTile[];
    studentUpcoming?: UpcomingData;
    studentSubmissions?: SubmissionMonth[];
    approverSummary?: ApproverSummary;
    approverNeedsReview?: ApproverNeedsReview;
    approverInProgress?: InProgressRow[];
    approverComingUp?: ComingUpEvent[];
    approverRecentDecisions?: RecentDecision[];
};

const STUDENT_DEFERRED_KEYS = [
    'studentKpis',
    'studentNeedsAction',
    'studentTracker',
    'studentRequirements',
    'studentQuickSubmit',
    'studentUpcoming',
    'studentSubmissions',
];

const APPROVER_DEFERRED_KEYS = [
    'approverSummary',
    'approverNeedsReview',
    'approverInProgress',
    'approverComingUp',
    'approverRecentDecisions',
];

export default function Dashboard({
    studentDashboard,
    pendingJoinRequest,
    approverDashboard,
    studentKpis,
    studentNeedsAction,
    studentTracker,
    studentRequirements,
    studentQuickSubmit,
    studentUpcoming,
    studentSubmissions,
    approverSummary,
    approverNeedsReview,
    approverInProgress,
    approverComingUp,
    approverRecentDecisions,
}: Props) {
    const { auth } = usePage().props;
    const accountStatus = auth.user.account_status;

    if (accountStatus === 'unverified') {
        return (
            <>
                <Head title="Dashboard" />
                <div className="mx-auto w-full max-w-2xl">
                    <PageNotice tone="info" icon={Hourglass} title="Pending SDAO verification.">
                        <p>
                            Your account is awaiting review. Once SDAO verifies
                            it, you&apos;ll be able to be bound as an
                            organization officer and submit documents.
                        </p>
                        {pendingJoinRequest ? (
                            <p className="mt-1">
                                You also have a pending request to join{' '}
                                <strong>
                                    {pendingJoinRequest.organizationName}
                                </strong>
                                . It is waiting on both your account
                                verification and the approval of its adviser or
                                officers.
                            </p>
                        ) : (
                            <p className="mt-1">
                                There&apos;s nothing else to do right now.
                                Check back later.
                            </p>
                        )}
                    </PageNotice>
                </div>
            </>
        );
    }

    if (accountStatus === 'rejected') {
        return (
            <>
                <Head title="Dashboard" />
                <div className="mx-auto w-full max-w-2xl">
                    <PageNotice tone="destructive" urgent icon={Ban} title="Account not approved.">
                        <p>
                            SDAO reviewed your registration and it was not
                            approved.
                        </p>
                        <p className="mt-1">
                            Contact SDAO directly if you believe this was a
                            mistake.
                        </p>
                    </PageNotice>
                </div>
            </>
        );
    }

    const hasAnyCard = Boolean(studentDashboard || approverDashboard);

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6">
                {approverDashboard ? (
                    <ApproverGreeting header={approverDashboard} />
                ) : (
                    <PageHeader
                        title="Dashboard"
                        subtitle="What needs your attention and where things stand"
                    />
                )}

                {!hasAnyCard ? (
                    <div className="mx-auto w-full max-w-2xl">
                        {pendingJoinRequest ? (
                            <PageNotice tone="info" icon={Hourglass} title="Join request pending.">
                                <p>
                                    Your request to join{' '}
                                    <strong>
                                        {pendingJoinRequest.organizationName}
                                    </strong>{' '}
                                    is waiting on its adviser or an active
                                    officer. You&apos;ll be notified as soon as
                                    it&apos;s decided.
                                </p>
                            </PageNotice>
                        ) : auth.canProposeOrganization ? (
                            <PageNotice
                                tone="neutral"
                                icon={FilePlus2}
                                title="Ready to get your organization set up?"
                            >
                                <p>
                                    Your account is verified and you
                                    aren&apos;t affiliated with an organization
                                    yet. If your organization isn&apos;t
                                    registered in the system, you can submit its
                                    registration now. SDAO will review it, and
                                    you&apos;ll be bound as its president once
                                    it&apos;s approved.
                                </p>
                                <p className="mt-1">
                                    Already part of an existing organization?
                                    Search for it and send a request to join
                                    instead.
                                </p>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <Button asChild>
                                        <Link href={registrations.create()}>
                                            Submit Registration
                                        </Link>
                                    </Button>
                                    <Button asChild variant="outline">
                                        <Link href={organizationsJoin.create()}>
                                            <UserPlus data-icon="inline-start" />
                                            Join an Organization
                                        </Link>
                                    </Button>
                                </div>
                            </PageNotice>
                        ) : (
                            <PageNotice tone="neutral" icon={UserCog} title="Nothing to do yet.">
                                <p>
                                    Your account is verified, but you
                                    aren&apos;t bound to an organization or an
                                    approval role yet.
                                </p>
                                <p className="mt-1">
                                    Search for your organization and send a
                                    request to join, or check the{' '}
                                    <Link
                                        href={calendar.index()}
                                        className="underline"
                                    >
                                        Venue Calendar
                                    </Link>{' '}
                                    in the meantime.
                                </p>
                                <Button
                                    asChild
                                    className="mt-3"
                                    variant="outline"
                                >
                                    <Link href={organizationsJoin.create()}>
                                        <UserPlus data-icon="inline-start" />
                                        Join an Organization
                                    </Link>
                                </Button>
                            </PageNotice>
                        )}
                    </div>
                ) : (
                    <>
                        {studentDashboard && (
                            <Deferred
                                data={STUDENT_DEFERRED_KEYS}
                                fallback={<StudentDashboardSkeleton />}
                            >
                                {studentKpis &&
                                studentNeedsAction &&
                                studentTracker &&
                                studentRequirements &&
                                studentQuickSubmit &&
                                studentUpcoming &&
                                studentSubmissions ? (
                                    <StudentDashboard
                                        meta={studentDashboard}
                                        needsAction={studentNeedsAction}
                                        tracker={studentTracker}
                                        requirements={studentRequirements}
                                        kpis={studentKpis}
                                        quickSubmit={studentQuickSubmit}
                                        upcoming={studentUpcoming}
                                        submissions={studentSubmissions}
                                    />
                                ) : (
                                    <StudentDashboardSkeleton />
                                )}
                            </Deferred>
                        )}

                        {approverDashboard && (
                            <Deferred
                                data={APPROVER_DEFERRED_KEYS}
                                fallback={<ApproverDashboardSkeleton />}
                            >
                                {approverSummary &&
                                approverNeedsReview &&
                                approverInProgress &&
                                approverComingUp &&
                                approverRecentDecisions ? (
                                    <ApproverDashboard
                                        header={approverDashboard}
                                        summary={approverSummary}
                                        needsReview={approverNeedsReview}
                                        inProgress={approverInProgress}
                                        comingUp={approverComingUp}
                                        recentDecisions={
                                            approverRecentDecisions
                                        }
                                    />
                                ) : (
                                    <ApproverDashboardSkeleton />
                                )}
                            </Deferred>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
