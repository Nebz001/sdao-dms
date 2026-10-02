import type { ComponentProps } from 'react';
import { Badge } from '@/components/ui/badge';
import { labelFor, toneFor } from '@/lib/status-tones';
import type { StatusDomain, Tone } from '@/lib/status-tones';
import { cn } from '@/lib/utils';

/**
 * The one status badge: small, outlined, uppercase and bold, with a thin
 * border and a very light tint of the same color. Never a solid fill. The
 * text uses the tone's "-foreground" token, which is the darker shade in the
 * light theme, so it stays readable on its own tint (see the contrast table in
 * the PR notes: every tone is 4.5:1 or better in both themes). Neutral is the
 * gray tone for "nothing is happening" states such as Draft or Inactive.
 *
 * `border-<tone>/40` is explicit on purpose: app.css's `* { border-border }`
 * base rule would otherwise paint a zinc outline around every chip.
 */
const TONE_STYLES: Record<Tone, string> = {
    success: 'border-success/40 bg-success/10 text-success-foreground',
    info: 'border-info/40 bg-info/10 text-info-foreground',
    warning: 'border-warning/40 bg-warning/10 text-warning-foreground',
    destructive: 'border-destructive/40 bg-destructive/10 text-destructive-foreground',
    neutral: 'border-muted-foreground/40 bg-muted-foreground/10 text-foreground/75',
};

type ToneBadgeProps = Omit<ComponentProps<typeof Badge>, 'variant'> & {
    tone: Tone;
};

/**
 * The primitive every status badge is built on. Pages never style a badge
 * themselves: they pass a status to one of the wrappers below, or a tone and
 * words to this component when no status domain fits.
 */
export function ToneBadge({ tone, className, children, ...props }: ToneBadgeProps) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'text-[0.65rem] font-semibold tracking-wide uppercase',
                TONE_STYLES[tone],
                className,
            )}
            {...props}
        >
            {children}
        </Badge>
    );
}

type DomainBadgeProps = {
    status: string;
    className?: string;
};

function DomainBadge({
    domain,
    status,
    className,
}: DomainBadgeProps & { domain: StatusDomain }) {
    return (
        <ToneBadge tone={toneFor(domain, status)} className={className}>
            {labelFor(domain, status)}
        </ToneBadge>
    );
}

/** A document's status: draft, in review, returned, approved, rejected. */
export function StatusBadge({ status, className }: DomainBadgeProps) {
    return <DomainBadge domain="document" status={status} className={className} />;
}

/** A document-transition action: submitted, approved, returned, and so on. */
export function ActionBadge({
    action,
    className,
}: {
    action: string;
    className?: string;
}) {
    return <DomainBadge domain="action" status={action} className={className} />;
}

/** An organization's derived status: active, pending review, needs renewal, inactive. */
export function OrganizationStatusBadge({ status, className }: DomainBadgeProps) {
    return <DomainBadge domain="organization" status={status} className={className} />;
}

/** A join request or officer change request: pending, approved, declined. */
export function RequestStatusBadge({ status, className }: DomainBadgeProps) {
    return <DomainBadge domain="request" status={status} className={className} />;
}

/** An account's verification state: pending verification, verified, not approved. */
export function AccountStatusBadge({ status, className }: DomainBadgeProps) {
    return <DomainBadge domain="account" status={status} className={className} />;
}

/** A renewal state, or `due` for the organization "renewal due" flag. */
export function RenewalBadge({ status, className }: DomainBadgeProps) {
    return <DomainBadge domain="renewal" status={status} className={className} />;
}

/** A requirements checklist state: done, in review, missing, not due, info. */
export function RequirementBadge({ status, className }: DomainBadgeProps) {
    return <DomainBadge domain="requirement" status={status} className={className} />;
}

/** A venue booking: confirmed or tentative. */
export function VenueStatusBadge({ status, className }: DomainBadgeProps) {
    return <DomainBadge domain="venue" status={status} className={className} />;
}

/** A short flag: resubmitted, urgent, flagged for revision, deactivated. */
export function FlagBadge({ flag, className }: { flag: string; className?: string }) {
    return <DomainBadge domain="flag" status={flag} className={className} />;
}
