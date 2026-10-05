import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    CircleCheck,
    TriangleAlert,
    UserRoundX,
    UsersRound,
} from 'lucide-react';
import SegmentedBar from '@/components/review-queue/segmented-bar';
import StatCard, { StatValue } from '@/components/review-queue/stat-card';
import { ToneBadge } from '@/components/status-badge';
import { splitAccountName } from '@/lib/account-name';
import { ROLE_GROUPS } from './types';
import type { ApproverStats } from './types';

/** Organization chips shown before "+N more". */
const CHIP_LIMIT = 3;

export function ActiveApproversCard({
    stats,
}: {
    stats: ApproverStats['active'];
}) {
    const segments = ROLE_GROUPS.map((g) => ({
        label: g.legend,
        count: stats.byGroup[g.key],
        className: g.barClass,
    })).filter((s) => s.count > 0);

    return (
        <StatCard icon={UsersRound} title="Active approvers">
            <StatValue>{stats.total}</StatValue>
            <SegmentedBar
                segments={segments}
                ariaLabel={`Active approvers by role: ${segments.map((s) => `${s.count} ${s.label}`).join(', ')}`}
            />
        </StatCard>
    );
}

/**
 * Warning tone while any approved organization has no adviser (that blocks its
 * approvals); calm tone, no alarm accent, once none is missing.
 */
export function MissingAdviserCard({
    stats,
}: {
    stats: ApproverStats['missingAdviser'];
}) {
    const missing = stats.count > 0;
    const shown = stats.organizations.slice(0, CHIP_LIMIT);
    const rest = stats.organizations.length - shown.length;

    return (
        <StatCard
            icon={missing ? TriangleAlert : CircleCheck}
            title="Missing an adviser"
            tone={missing ? 'warning' : 'default'}
        >
            <div className="flex items-center justify-between gap-3">
                <StatValue>{stats.count}</StatValue>
                {missing && (
                    // Backed by the plain card surface so the tint does not stack on the card tint (keeps light-theme contrast at 4.5:1).
                    <span className="rounded-full bg-card">
                        <ToneBadge
                            tone="warning"
                            className="text-xs tracking-normal normal-case"
                        >
                            Blocks approvals
                        </ToneBadge>
                    </span>
                )}
            </div>
            {missing ? (
                <>
                    <ul
                        className="flex flex-wrap gap-1.5"
                        aria-label="Organizations without an adviser"
                    >
                        {shown.map((o) => (
                            <li
                                key={o.id}
                                className="max-w-full rounded-full bg-card"
                            >
                                <ToneBadge
                                    tone="warning"
                                    className="max-w-full text-xs tracking-normal normal-case"
                                >
                                    <span className="truncate">{o.name}</span>
                                </ToneBadge>
                            </li>
                        ))}
                        {rest > 0 && (
                            <li className="rounded-full bg-card">
                                <ToneBadge
                                    tone="neutral"
                                    className="text-xs tracking-normal normal-case"
                                >
                                    +{rest} more
                                </ToneBadge>
                            </li>
                        )}
                    </ul>
                    <Link
                        href={stats.href}
                        className="mt-auto inline-flex items-center gap-1.5 text-sm font-medium text-warning-foreground hover:underline"
                    >
                        Assign advisers
                        <ArrowRight className="size-4" aria-hidden />
                    </Link>
                </>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Every approved organization has an adviser.
                </p>
            )}
        </StatCard>
    );
}

export function DeactivatedCard({
    stats,
}: {
    stats: ApproverStats['deactivated'];
}) {
    return (
        <StatCard icon={UserRoundX} title="Deactivated">
            <StatValue>{stats.count}</StatValue>
            {stats.latest ? (
                <div className="flex flex-col gap-0.5 text-sm">
                    <p>
                        <span className="text-muted-foreground">
                            Most recent:{' '}
                        </span>
                        <span className="font-medium break-words">
                            {splitAccountName(stats.latest.name).name}
                        </span>
                    </p>
                    <p className="text-muted-foreground">
                        <time dateTime={stats.latest.at}>
                            {new Date(stats.latest.at).toLocaleDateString(
                                undefined,
                                { dateStyle: 'long' },
                            )}
                        </time>
                    </p>
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    No accounts have been deactivated.
                </p>
            )}
        </StatCard>
    );
}
