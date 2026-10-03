import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: ReactNode;
    /**
     * Always a fixed string, identical for every user and every state. Data
     * driven messages belong in a PageNotice under the header instead.
     */
    subtitle: ReactNode;
    /** Sits next to the title, e.g. an organization status badge. */
    badge?: ReactNode;
    /** A small label above the title, e.g. a document type badge. */
    eyebrow?: ReactNode;
    /** Buttons aligned to the end of the header. */
    actions?: ReactNode;
};

/**
 * The single page header for every screen: title, one static sublabel and
 * optional badge and actions. Wraps cleanly on a phone.
 */
export default function PageHeader({
    title,
    subtitle,
    badge,
    eyebrow,
    actions,
}: PageHeaderProps) {
    return (
        <header className="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
            <div className="min-w-0 flex-1 basis-72">
                {eyebrow && <div className="mb-2">{eyebrow}</div>}
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        {title}
                    </h1>
                    {badge}
                </div>
                <div className="mt-1 text-sm text-muted-foreground">{subtitle}</div>
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">{actions}</div>
            )}
        </header>
    );
}
