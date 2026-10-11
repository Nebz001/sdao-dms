import type { HomeBannerIcon, HomeBannerTone } from '@/components/home-banner';
import { pluralize } from '@/lib/student-home';

export type TrackStepState = 'done' | 'current' | 'returned' | 'rejected' | 'upcoming';

export type TrackPerson = {
    name: string;
    /** "College Dean, SACE" */
    role: string;
    email: string | null;
    /** Already approved this step (a two-person step, while it is still open). */
    approved: boolean;
};

export type TrackStep = {
    key: string;
    label: string;
    state: TrackStepState;
    date: string | null;
    hereDays: number | null;
    isNext: boolean;
    muted: boolean;
    popover: {
        status: string;
        /** The role (and scope) of the seat, e.g. "College Dean, SACE". */
        roleText: string;
        receivedAt: string | null;
        /** What was decided at this step, and when; null while it is still ahead or open. */
        decision: { label: 'Approved' | 'Returned' | 'Rejected'; at: string | null } | null;
        /** Days the current step has been waiting; null for any other step. */
        waitingDays: number | null;
        people: TrackPerson[];
    } | null;
};

export type TrackRow = {
    id: number;
    formType: string;
    title: string;
    showFormType: boolean;
    period: string | null;
    formTypeLabel: string;
    submittedAt: string | null;
    status: string;
    action: { label: string; href: string; kind: 'fix' | 'continue' | 'view' };
    steps: TrackStep[] | null;
    note: {
        role: string;
        comment: string | null;
        remarks: { name: string; body: string }[];
    } | null;
};

export type TrackTab = 'all' | 'action' | 'moving' | 'approved' | 'stopped';

export type TrackSummary = {
    returned: number;
    drafts: number;
    moving: number;
    approved: number;
    approvedYear: number;
    stopped: number;
    total: number;
    movingByStep: { label: string; count: number }[];
};

export type BannerKind = 'action' | 'moving' | 'approved' | 'quiet' | 'empty';

export type TrackBanner = {
    kind: BannerKind;
    tone: HomeBannerTone;
    icon: HomeBannerIcon;
    title: string;
    description: string;
    button: string;
    tab: TrackTab | null;
};

const isAre = (n: number) => (n === 1 ? 'is' : 'are');

/** "1 is with the College Dean, 2 are with SDAO": the people the moving documents wait on. */
export function movingLocations(byStep: TrackSummary['movingByStep']): string {
    return byStep
        .map(({ label, count }) => `${count} ${isAre(count)} with ${label === 'SDAO' ? label : `the ${label}`}`)
        .join(', ');
}

/**
 * Which banner to show, in this order: something needs the officer (returned
 * or an unfinished draft), documents still moving, everything approved,
 * nothing filed. A student whose only documents were stopped gets a quiet
 * banner so the routing page is still one tap away.
 */
export function bannerFor(summary: TrackSummary): TrackBanner {
    const needs = summary.returned + summary.drafts;

    if (needs > 0) {
        const parts = [
            summary.returned > 0 ? `${summary.returned} returned for revision.` : null,
            summary.drafts > 0 ? `${pluralize(summary.drafts, 'unfinished draft')}.` : null,
            summary.moving > 0
                ? `${pluralize(summary.moving, 'document')} ${isAre(summary.moving)} moving through the approval chain.`
                : null,
        ].filter(Boolean);

        return {
            kind: 'action',
            tone: 'orange',
            icon: 'warning',
            title: `${pluralize(needs, 'document')} ${needs === 1 ? 'needs' : 'need'} your action`,
            description: parts.join(' '),
            button: 'Track my documents',
            tab: 'action',
        };
    }

    if (summary.moving > 0) {
        const where = movingLocations(summary.movingByStep);

        return {
            kind: 'moving',
            tone: 'blue',
            icon: 'clock',
            title: `${pluralize(summary.moving, 'document')} ${isAre(summary.moving)} moving`,
            description: `Nothing needs your action.${where ? ` ${where}.` : ''}`,
            button: 'Track my documents',
            tab: 'moving',
        };
    }

    if (summary.approvedYear > 0) {
        return {
            kind: 'approved',
            tone: 'green',
            icon: 'check-circle',
            title: 'All documents approved',
            description:
                summary.approved > 0
                    ? `You’re all caught up. ${pluralize(summary.approved, 'document')} approved this term.`
                    : 'You’re all caught up.',
            button: 'See document routing',
            tab: 'approved',
        };
    }

    if (summary.total > 0) {
        return {
            kind: 'quiet',
            tone: 'gray',
            icon: 'clock',
            title: 'Nothing is moving',
            description: 'None of your documents is waiting on anyone. You can still see how each one went.',
            button: 'Track my documents',
            tab: 'all',
        };
    }

    return {
        kind: 'empty',
        tone: 'gray',
        icon: 'file-plus',
        title: 'No documents yet this term',
        description: 'Start with an activity proposal. You can track it here once it is sent.',
        button: 'Start a proposal',
        tab: null,
    };
}

/** "Here now · today" / "Here now · 1 day" / "Here now · 3 days" */
export function hereLabel(days: number | null): string {
    return !days ? 'Here now · today' : `Here now · ${pluralize(days, 'day')}`;
}

/** "Oct 7", in Asia/Manila. */
export function shortDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'Asia/Manila' });
}

/** "Oct 6, 2026", in Asia/Manila. */
export function longDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'Asia/Manila',
    });
}

/** The second line under a step, from its state. */
export function stepCaption(step: TrackStep): string {
    switch (step.state) {
        case 'done':
            return step.date ? (step.key === 'submitted' || step.key === 'approved' ? shortDate(step.date) : `Approved ${shortDate(step.date)}`) : '';
        case 'current':
            return hereLabel(step.hereDays);
        case 'returned':
            return step.date ? `Returned ${shortDate(step.date)}` : 'Returned';
        case 'rejected':
            return step.date ? `Rejected ${shortDate(step.date)}` : 'Rejected';
        default:
            return step.isNext ? 'Next' : 'Waiting';
    }
}
