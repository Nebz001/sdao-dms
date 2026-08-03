import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

type StatTileProps = {
    label: string;
    count: number;
    href: string;
    icon: LucideIcon;
};

/**
 * A single clickable count — the admin dashboard's "quick links" strip.
 * Distinct from QueueStatStrip (a non-clickable stat row inside one Card):
 * every stat here is a genuinely new destination that had no visibility
 * before this dashboard existed (Pending Accounts, unassigned advisers),
 * so the whole tile is a link rather than a plain number.
 */
export default function StatTile({ label, count, href, icon: Icon }: StatTileProps) {
    return (
        <Card className="transition-colors hover:border-primary/40">
            <CardContent>
                <Link
                    href={href}
                    className="flex items-start justify-between gap-2 rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <div>
                        <p className="text-sm text-muted-foreground">{label}</p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">{count}</p>
                    </div>
                    <Icon className="size-5 shrink-0 text-muted-foreground" />
                </Link>
            </CardContent>
        </Card>
    );
}
