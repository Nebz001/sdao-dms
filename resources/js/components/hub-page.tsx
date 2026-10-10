import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, Info, LayoutGrid } from 'lucide-react';
import IconTile from '@/components/icon-tile';
import { ToneBadge } from '@/components/status-badge';
import StudentPageHeader from '@/components/student-page-header';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { HUB_GROUP, OPTION_CHIP_KEY, optionMeta } from '@/lib/hub-options';
import type { HubKey } from '@/lib/hub-options';
import { buildNavSections } from '@/lib/nav-config';
import { homeCardCopy } from '@/lib/student-home';
import * as activityProposals from '@/routes/activity-proposals';
import * as calendar from '@/routes/calendar';
import type { NavItem } from '@/types';
import { isNavGroup } from '@/types/navigation';

export type HubChip = { label: string; tone: 'neutral' | 'warning' };

type Option = {
    title: string;
    href: NavItem['href'];
    badge?: number;
    chip?: HubChip;
};

const SUBTITLES: Record<HubKey, (org: string | null) => string> = {
    submit: (org) =>
        org
            ? `Choose the form you want to file for ${org}`
            : 'Choose the form you want to file',
    'my-documents': (org) =>
        org
            ? `Pick a list to see what ${org} has filed`
            : 'Pick a list to see your documents',
    review: () => 'Requests waiting on you',
};

/**
 * One hub page (Submit, My Documents or Review). The option cards are the
 * nav config's own items for that group, so the options, their links and who
 * may see them are exactly what the sidebar and the home card already use.
 * The only extras are real: a count on each My Documents option, status chips
 * from the server, and a "Continue a Draft" option when a draft actually
 * exists.
 */
export default function HubPage({
    hub,
    organizationName,
    chips,
}: {
    hub: HubKey;
    organizationName: string | null;
    chips: Record<string, HubChip>;
}) {
    const { auth, navCounts } = usePage().props;
    const groupTitle = HUB_GROUP[hub];
    const group = buildNavSections(auth, navCounts)
        .flatMap((section) => section.entries)
        .find((entry) => isNavGroup(entry) && entry.title === groupTitle);
    const copy = homeCardCopy(groupTitle);
    const icon = group?.icon ?? LayoutGrid;

    const options: Option[] =
        group && isNavGroup(group)
            ? group.items.map((item) => ({
                  title: item.title,
                  href: item.href,
                  badge: item.badge,
                  chip: chips[OPTION_CHIP_KEY[item.title]],
              }))
            : [];

    // Drafts live on the proposals list (Activity Proposal is the only form
    // that is ever saved as a draft), so the extra option opens it there.
    if (hub === 'submit' && options.length > 0 && chips.draft) {
        options.push({
            title: 'Continue a Draft',
            href: activityProposals.index({ query: { tab: 'draft' } }),
            chip: chips.draft,
        });
    }

    return (
        <div className="flex flex-col gap-6">
            <StudentPageHeader
                icon={icon}
                tone={copy?.tone ?? 'slate'}
                title={groupTitle}
                subtitle={SUBTITLES[hub](organizationName)}
            />

            {options.length === 0 ? (
                <Empty className="border">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <LayoutGrid />
                        </EmptyMedia>
                        <EmptyTitle>Nothing to open here yet</EmptyTitle>
                        <EmptyDescription>
                            There is nothing in {groupTitle} for your account
                            right now.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <ul className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {options.map((option) => {
                        const meta = optionMeta(groupTitle, option.title);

                        return (
                            <li key={option.title}>
                                <Link
                                    href={option.href}
                                    prefetch
                                    className="group flex h-full items-center gap-4 rounded-xl border border-card-border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-foreground/30 focus-visible:focus-ring"
                                >
                                    <IconTile
                                        icon={meta.icon}
                                        tone={meta.tone}
                                    />
                                    <span className="flex min-w-0 flex-1 flex-col items-start gap-1">
                                        <span className="text-base font-semibold">
                                            {option.title}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {meta.description}
                                        </span>
                                        {option.chip && (
                                            <ToneBadge
                                                tone={option.chip.tone}
                                                className="mt-1 tracking-normal normal-case"
                                            >
                                                {option.chip.label}
                                            </ToneBadge>
                                        )}
                                    </span>
                                    {option.badge !== undefined && (
                                        <span
                                            data-slot="hub-option-count"
                                            className="inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full bg-muted px-2 text-xs font-semibold text-foreground tabular-nums"
                                        >
                                            {option.badge}
                                            <span className="sr-only">
                                                {' '}
                                                {option.badge === 1
                                                    ? 'document'
                                                    : 'documents'}
                                            </span>
                                        </span>
                                    )}
                                    <ChevronRight
                                        aria-hidden
                                        className="size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none"
                                    />
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}

            {hub === 'submit' && options.length > 0 && (
                <p className="flex items-center gap-2 rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground">
                    <Info aria-hidden className="size-4 shrink-0" />
                    <span>
                        Not sure which form to use? Check the{' '}
                        <Link
                            href={calendar.index()}
                            className="font-medium text-foreground underline underline-offset-2"
                        >
                            venue calendar
                        </Link>{' '}
                        first, then file an Activity Proposal for any event you
                        plan to hold.
                    </span>
                </p>
            )}
        </div>
    );
}
