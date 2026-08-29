import { Head, Link, router } from '@inertiajs/react';
import { History } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import QueueStatStrip from '@/components/queue-stat-strip';
import { RelativeTime } from '@/components/relative-time';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { statusLabel } from '@/lib/utils';
import * as activityLog from '@/routes/admin/activity';

type FormTypeOption = { value: string; label: string };
type ActionOption = { value: string };

type ActivityEntry = {
    id: number;
    actorName: string;
    action: string;
    documentTitle: string;
    formTypeLabel: string;
    organizationName: string;
    createdAt: string;
    href: string;
};

type Props = {
    transitions: {
        data: ActivityEntry[];
        meta: {
            current_page: number;
            last_page: number;
            from: number | null;
            to: number | null;
            total: number;
        };
        links: { prev: string | null; next: string | null };
    };
    filters: {
        form_type: string | null;
        action: string | null;
        search: string;
    };
    formTypes: FormTypeOption[];
    actions: ActionOption[];
    stats: { total: number };
};

const ALL_TYPES = 'all';
const ALL_ACTIONS = 'all';

export default function ActivityLogIndex({
    transitions,
    filters,
    formTypes,
    actions,
    stats,
}: Props) {
    const [formType, setFormType] = useState(filters.form_type ?? ALL_TYPES);
    const [action, setAction] = useState(filters.action ?? ALL_ACTIONS);
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const isFirstRender = useRef(true);

    function reload(params: Record<string, string>) {
        setLoading(true);
        router.get(activityLog.index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['transitions', 'filters', 'stats'],
            onFinish: () => setLoading(false),
        });
    }

    // A single debounced effect covers all three filters (select changes and
    // keystrokes alike) so clearing/combining filters triggers exactly one
    // reload instead of racing separate effects per field — same pattern as
    // the Document Archive page.
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        debounceTimer.current = setTimeout(() => {
            const params: Record<string, string> = {};

            if (formType !== ALL_TYPES) {
                params.form_type = formType;
            }

            if (action !== ALL_ACTIONS) {
                params.action = action;
            }

            if (search.trim() !== '') {
                params.search = search.trim();
            }

            reload(params);
        }, 400);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [formType, action, search]);

    const hasFilters =
        formType !== ALL_TYPES ||
        action !== ALL_ACTIONS ||
        search.trim() !== '';

    function clearFilters() {
        setFormType(ALL_TYPES);
        setAction(ALL_ACTIONS);
        setSearch('');
    }

    function goToPage(url: string | null) {
        if (!url) {
            return;
        }

        setLoading(true);
        router.get(
            url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['transitions', 'filters', 'stats'],
                onFinish: () => setLoading(false),
            },
        );
    }

    return (
        <>
            <Head title="Activity Log" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Activity Log
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Every submission, approval, return, and rejection across
                        every organization and form type.
                    </p>
                </div>

                <QueueStatStrip
                    stats={[
                        {
                            label: 'Total Events',
                            value: String(stats.total),
                            count: stats.total,
                        },
                    ]}
                />

                <Card>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <div className="grid gap-2">
                            <Label htmlFor="activity-form-type">
                                Form type
                            </Label>
                            <Select
                                value={formType}
                                onValueChange={setFormType}
                            >
                                <SelectTrigger
                                    id="activity-form-type"
                                    className="w-full sm:w-56"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_TYPES}>
                                        All types
                                    </SelectItem>
                                    {formTypes.map((t) => (
                                        <SelectItem
                                            key={t.value}
                                            value={t.value}
                                        >
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="activity-action">Action</Label>
                            <Select value={action} onValueChange={setAction}>
                                <SelectTrigger
                                    id="activity-action"
                                    className="w-full sm:w-48"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_ACTIONS}>
                                        All actions
                                    </SelectItem>
                                    {actions.map((a) => (
                                        <SelectItem
                                            key={a.value}
                                            value={a.value}
                                        >
                                            {statusLabel(a.value)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="activity-search">Search</Label>
                            <Input
                                id="activity-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Document title or organization…"
                            />
                        </div>

                        {hasFilters && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={clearFilters}
                            >
                                Clear filters
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Events</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <Skeleton key={i} className="h-12 w-full" />
                                ))}
                            </div>
                        ) : transitions.data.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <History />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {hasFilters
                                            ? 'No activity matches these filters'
                                            : 'Nothing has happened yet'}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {hasFilters
                                            ? 'Try a different form type, action, or search term.'
                                            : 'Submissions and approvals will show up here as they happen.'}
                                    </EmptyDescription>
                                    {hasFilters && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={clearFilters}
                                        >
                                            Clear filters
                                        </Button>
                                    )}
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {transitions.data.map((entry) => (
                                    <div
                                        key={entry.id}
                                        className="flex items-center justify-between gap-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <Link
                                                href={entry.href}
                                                className="truncate font-medium hover:underline"
                                            >
                                                {entry.documentTitle}
                                            </Link>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {entry.actorName}{' '}
                                                {statusLabel(
                                                    entry.action,
                                                ).toLowerCase()}{' '}
                                                · {entry.organizationName} ·{' '}
                                                {entry.formTypeLabel}
                                            </p>
                                        </div>
                                        <span className="shrink-0 text-sm text-muted-foreground">
                                            <RelativeTime dateString={entry.createdAt} />
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {!loading && transitions.data.length > 0 && (
                    <Card>
                        <CardContent className="flex items-center justify-between gap-4">
                            <p className="text-sm text-muted-foreground">
                                Showing {transitions.meta.from}–
                                {transitions.meta.to} of{' '}
                                {transitions.meta.total}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!transitions.links.prev}
                                    onClick={() =>
                                        goToPage(transitions.links.prev)
                                    }
                                >
                                    Previous
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!transitions.links.next}
                                    onClick={() =>
                                        goToPage(transitions.links.next)
                                    }
                                >
                                    Next
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

ActivityLogIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Activity Log' }],
};
