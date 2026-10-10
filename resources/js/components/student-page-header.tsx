import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import IconTile from '@/components/icon-tile';

/**
 * The header of every student hub and list page: the coloured icon tile, the
 * title with a one-line subtitle, and the primary action at the end. It is the
 * student-side sibling of PageHeader (which the sidebar pages keep).
 */
export default function StudentPageHeader({
    icon,
    title,
    subtitle,
    actions,
}: {
    icon: LucideIcon;
    title: string;
    subtitle: string;
    actions?: ReactNode;
}) {
    return (
        <header className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
            <div className="flex min-w-0 flex-1 basis-72 items-center gap-4">
                <IconTile icon={icon} size="lg" />
                <div className="min-w-0">
                    <h1 className="text-2xl font-bold tracking-tight text-balance sm:text-3xl">
                        {title}
                    </h1>
                    <p className="text-sm text-muted-foreground">{subtitle}</p>
                </div>
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
