import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import CenteredContainer from '@/components/centered-container';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type BlockedAction = {
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    /** A quiet text link instead of a button (for "Back to Home"). */
    quiet?: boolean;
};

type Props = {
    icon: LucideIcon;
    /** The page's one heading. */
    title: string;
    /** Two lines at most. Wrap the key values (organization, term) in <strong>. */
    body: ReactNode;
    primary?: BlockedAction;
    secondary?: BlockedAction;
    /** The page's form column, so the card is as wide as the form it stands in for. */
    width?: '2xl' | '3xl';
};

/**
 * A page the user cannot use right now: one centered card with a dashed
 * border, an icon in a soft tile, a bold title, a short reason and the most
 * useful next step as a button (secondary on the left, primary on the right;
 * stacked and full width on a phone). It replaces the blue notice that used to
 * stand in for such a page. Callers only pass an action the user can really
 * open, never a link that would end in a 403.
 */
export default function BlockedState({ icon: Icon, title, body, primary, secondary, width = '3xl' }: Props) {
    return (
        <CenteredContainer maxWidth={width}>
            <section className="flex flex-col items-center rounded-xl border border-dashed px-6 py-12 text-center sm:py-14">
                <span
                    aria-hidden
                    className="flex size-14 items-center justify-center rounded-xl bg-muted text-foreground [&_svg]:size-7"
                >
                    <Icon />
                </span>
                <h1 className="mt-5 text-xl font-bold tracking-tight text-balance">{title}</h1>
                <p className="mt-2 max-w-md text-sm leading-relaxed text-pretty text-muted-foreground [&_strong]:font-semibold [&_strong]:text-foreground">
                    {body}
                </p>
                {(primary || secondary) && (
                    <div className="mt-6 flex w-full flex-col-reverse items-stretch gap-2 sm:w-auto sm:flex-row sm:items-center">
                        {secondary && <ActionLink action={secondary} variant="secondary" />}
                        {primary && <ActionLink action={primary} variant="default" />}
                    </div>
                )}
            </section>
        </CenteredContainer>
    );
}

function ActionLink({ action, variant }: { action: BlockedAction; variant: 'secondary' | 'default' }) {
    return (
        <Button
            asChild
            variant={action.quiet ? 'ghost' : variant}
            className={cn(action.quiet && 'text-muted-foreground')}
        >
            <Link href={action.href}>{action.label}</Link>
        </Button>
    );
}
