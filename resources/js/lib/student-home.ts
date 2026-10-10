import type { LucideIcon } from 'lucide-react';
import type { TileTone } from '@/components/icon-tile';
import type { NavEntry, NavItem, NavSection } from '@/types';
import { isNavGroup } from '@/types/navigation';


export type HomeCardTone = TileTone;

export type HomeCard = {
    key: string;
    title: string;
    description: string;
    icon: LucideIcon;
    tone: HomeCardTone;
    /** The sub pages, for the "N options" count and for search. Null on a one-page card. */
    items: NavItem[] | null;
    /** A card with several options opens its hub page; any other goes straight to its page. */
    href: NavItem['href'];
    /** Sum of the sub pages' count badges (same rule as the sidebar group). */
    badge: number | null;
};

/**
 * What each home card says and how it is tinted. Keyed by the sidebar entry's
 * title: the destinations, sub links, icons and role gating all come from the
 * sidebar's own nav entries, so this only owns the words and the colour.
 */
const CARD_COPY: {
    title: string;
    description: string;
    tone: HomeCardTone;
}[] = [
    {
        title: 'Submit',
        description:
            'File a new activity proposal, after-activity report, or registration.',
        tone: 'blue',
    },
    {
        title: 'My Documents',
        description:
            'See your drafts, submitted, returned, and approved documents.',
        tone: 'green',
    },
    {
        title: 'Review',
        description: 'Check documents that need your sign-off as an officer.',
        tone: 'amber',
    },
    {
        title: 'Venue Calendar',
        description: 'See which venues are free before you plan an activity.',
        tone: 'teal',
    },
    {
        title: 'My Organization',
        description:
            'View your org profile, officers, and registration status.',
        tone: 'purple',
    },
    {
        title: 'Request Officer Change',
        description: 'Ask SDAO to update who holds an officer position.',
        tone: 'orange',
    },
    {
        title: 'Join an Organization',
        description:
            'Find your organization and ask its adviser or officers to let you in.',
        tone: 'sky',
    },
];

function findEntry(
    sections: NavSection[],
    title: string,
): NavEntry | undefined {
    return sections
        .flatMap((section) => section.entries)
        .find((entry) => entry.title === title);
}

/** Builds the cards the signed-in user may open, from the sidebar's own sections. */
/** The colour, words and icon a card has, by its nav title. Used by the hub page header too. */
export function homeCardCopy(title: string) {
    return CARD_COPY.find((copy) => copy.title === title);
}

export function buildHomeCards(sections: NavSection[]): HomeCard[] {
    const cards: HomeCard[] = [];

    for (const copy of CARD_COPY) {
        const entry = findEntry(sections, copy.title);

        if (!entry || !entry.icon) {
            continue;
        }

        const base = {
            key: copy.title,
            title: copy.title,
            description: copy.description,
            icon: entry.icon,
            tone: copy.tone,
        };

        if (isNavGroup(entry) && entry.items.length > 1 && entry.href) {
            const hasBadges = entry.items.some((i) => i.badge !== undefined);

            cards.push({
                ...base,
                items: entry.items,
                href: entry.href,
                badge: hasBadges
                    ? entry.items.reduce((sum, i) => sum + (i.badge ?? 0), 0)
                    : null,
            });
        } else {
            // A group with a single sub page has nothing to choose from, so
            // the card goes straight to it.
            const target = isNavGroup(entry) ? entry.items[0] : entry;

            cards.push({
                ...base,
                items: null,
                href: target.href,
                badge: null,
            });
        }
    }

    return cards;
}

/**
 * Client-side search over the cards and their sub links: a card stays if its
 * title or description matches, or if any of its options does.
 */
export function filterHomeCards(cards: HomeCard[], query: string): HomeCard[] {
    const needle = query.trim().toLowerCase();

    if (needle === '') {
        return cards;
    }

    const result: HomeCard[] = [];

    for (const card of cards) {
        const cardMatches =
            card.title.toLowerCase().includes(needle) ||
            card.description.toLowerCase().includes(needle);

        const optionMatches = (card.items ?? []).some((item) =>
            item.title.toLowerCase().includes(needle),
        );

        if (cardMatches || optionMatches) {
            result.push(card);
        }
    }

    return result;
}

export function pluralize(count: number, singular: string, plural?: string) {
    return `${count} ${count === 1 ? singular : (plural ?? `${singular}s`)}`;
}

/** "Good morning / afternoon / evening" by the wall-clock hour in Asia/Manila. */
export function greetingFor(now: Date = new Date()): string {
    const hour = Number(
        new Intl.DateTimeFormat('en-US', {
            hour: 'numeric',
            hourCycle: 'h23',
            timeZone: 'Asia/Manila',
        }).format(now),
    );

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 18) {
        return 'Good afternoon';
    }

    return 'Good evening';
}

export type BannerState = {
    kind: 'caught-up' | 'needs-action';
    title: string;
    description: string;
};

/**
 * The status banner's words. `needsActionTotal` counts returned documents and
 * unfinished drafts; `inChainTotal` is the tracker's total, which also lists
 * returned documents, so those are taken out to count only what is actually
 * waiting on an approver.
 */
export function bannerState({
    needsActionTotal,
    returnedCount,
    draftCount,
    inChainTotal,
}: {
    needsActionTotal: number;
    returnedCount: number;
    draftCount: number;
    inChainTotal: number;
}): BannerState {
    const moving = Math.max(0, inChainTotal - returnedCount);
    const chain =
        moving === 0
            ? 'No documents are moving through the approval chain.'
            : `${pluralize(moving, 'document')} ${moving === 1 ? 'is' : 'are'} moving through the approval chain.`;

    if (needsActionTotal === 0) {
        return {
            kind: 'caught-up',
            title: 'You’re all caught up',
            description: `Nothing needs your action. ${chain}`,
        };
    }

    const parts: string[] = [];

    if (returnedCount > 0) {
        parts.push(`${returnedCount} returned for revision`);
    }

    if (draftCount > 0) {
        parts.push(pluralize(draftCount, 'unfinished draft'));
    }

    return {
        kind: 'needs-action',
        title: `${pluralize(needsActionTotal, 'document')} ${needsActionTotal === 1 ? 'needs' : 'need'} your action`,
        description: `${parts.length > 0 ? `${parts.join(' and ')}. ` : ''}${chain}`,
    };
}
