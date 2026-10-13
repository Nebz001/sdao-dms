import {
    Calendar,
    Clock,
    MapPin,
    Pencil,
    Plus,
    Trash2,
    User,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import DateBadge from '@/components/date-badge';
import { formatPeso } from '@/components/document-view/format';
import { AddonInput, FormField } from '@/components/form-shell';
import PageNotice from '@/components/page-notice';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Toggle } from '@/components/ui/toggle';
import { BRAND_TINT_OUTLINE } from '@/lib/brand-tint';
import { notify } from '@/lib/toast';
import { cn } from '@/lib/utils';

export type SdgOption = { value: string; label: string };

/** The fields of one activity, exactly as the backend receives them. */
export type ActivityFields = {
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    description: string;
    sdg: string[];
    participant_program_assigned: string;
    budget: string;
};

/**
 * An activity on the page. `uid` is the row's identity on the client only: it
 * survives sorting, editing and deleting its neighbours, and is never sent.
 * `position` is the row's place in the order the server knows it by (the order
 * of the stored calendar, which `activity_{n}` flags refer to); it is null for
 * a row added on this page.
 */
export type EditorRow = ActivityFields & {
    uid: string;
    position: number | null;
};

export const emptyFields = (): ActivityFields => ({
    name: '',
    venue: '',
    activity_date: '',
    start_time: '',
    end_time: '',
    description: '',
    sdg: [],
    participant_program_assigned: '',
    budget: '',
});

let uidCounter = 0;

export function newUid(): string {
    uidCounter += 1;

    return `activity-${uidCounter}`;
}

export function toActivityFields(row: ActivityFields): ActivityFields {
    return {
        name: row.name,
        venue: row.venue,
        activity_date: row.activity_date,
        start_time: row.start_time,
        end_time: row.end_time,
        description: row.description,
        sdg: row.sdg,
        participant_program_assigned: row.participant_program_assigned,
        budget: row.budget,
    };
}

/** The server sends H:i, but a stored time can arrive as H:i:s; the H:i rule is strict. */
export function clockTime(value: string | null | undefined): string {
    return (value ?? '').slice(0, 5);
}

const SDG_SHORT_NAMES = [
    'No Poverty',
    'Zero Hunger',
    'Good Health',
    'Quality Education',
    'Gender Equality',
    'Clean Water',
    'Clean Energy',
    'Decent Work',
    'Industry and Innovation',
    'Reduced Inequalities',
    'Sustainable Cities',
    'Responsible Consumption',
    'Climate Action',
    'Life Below Water',
    'Life on Land',
    'Peace and Justice',
    'Partnerships',
];

/** "4. Quality Education" -> { number: 4, short: 'Quality Education', full: 'Quality Education' }. */
function sdgParts(option: SdgOption) {
    const match = /^(\d+)\.\s*(.*)$/.exec(option.label);
    const number = match ? Number(match[1]) : 0;

    return {
        number,
        short: SDG_SHORT_NAMES[number - 1] ?? option.label,
        full: match ? match[2] : option.label,
    };
}

function formatClock(value: string): string {
    const [h, m] = value.split(':').map(Number);

    if (Number.isNaN(h) || Number.isNaN(m)) {
        return value;
    }

    return `${h % 12 === 0 ? 12 : h % 12}:${String(m).padStart(2, '0')} ${h >= 12 ? 'PM' : 'AM'}`;
}

function sameFields(a: ActivityFields, b: ActivityFields): boolean {
    return (
        a.name === b.name &&
        a.venue === b.venue &&
        a.activity_date === b.activity_date &&
        a.start_time === b.start_time &&
        a.end_time === b.end_time &&
        a.description === b.description &&
        a.participant_program_assigned === b.participant_program_assigned &&
        a.budget === b.budget &&
        [...a.sdg].sort().join('|') === [...b.sdg].sort().join('|')
    );
}

/** The same rules, in the same words, as StoreActivityCalendarRequest. */
function validateFields(
    fields: ActivityFields,
    minDate: string | undefined,
): Partial<Record<keyof ActivityFields, string>> {
    const errors: Partial<Record<keyof ActivityFields, string>> = {};
    const required = (key: keyof ActivityFields, label: string) =>
        `The ${label} field is required.`;

    if (!fields.name.trim()) {
        errors.name = required('name', 'Activity Name');
    } else if (fields.name.length > 255) {
        errors.name =
            'The Activity Name field must not be greater than 255 characters.';
    }

    if (!fields.venue.trim()) {
        errors.venue = required('venue', 'Venue');
    } else if (fields.venue.length > 255) {
        errors.venue =
            'The Venue field must not be greater than 255 characters.';
    }

    if (!fields.activity_date) {
        errors.activity_date = required('activity_date', 'Date');
    } else if (minDate && fields.activity_date < minDate) {
        errors.activity_date =
            'The Date field must be a date after or equal to today.';
    }

    if (!fields.start_time) {
        errors.start_time = required('start_time', 'Start Time');
    }

    if (!fields.end_time) {
        errors.end_time = required('end_time', 'End Time');
    } else if (fields.start_time && fields.end_time <= fields.start_time) {
        errors.end_time = 'The End Time field must be a date after Start Time.';
    }

    if (fields.sdg.length === 0) {
        errors.sdg = required('sdg', 'SDG');
    }

    if (!fields.participant_program_assigned.trim()) {
        errors.participant_program_assigned = required(
            'participant_program_assigned',
            'Participant/Program Assigned',
        );
    } else if (fields.participant_program_assigned.length > 255) {
        errors.participant_program_assigned =
            'The Participant/Program Assigned field must not be greater than 255 characters.';
    }

    if (fields.budget === '') {
        errors.budget = required('budget', 'Budget');
    } else if (Number.isNaN(Number(fields.budget))) {
        errors.budget = 'The Budget field must be a number.';
    } else if (Number(fields.budget) < 0) {
        errors.budget = 'The Budget field must be at least 0.';
    }

    return errors;
}

type ConflictEntry = { name: string; organization: string };
type ConflictResult = {
    confirmed: ConflictEntry[];
    tentative: ConflictEntry[];
};

/**
 * The venue-conflict warnings the create page has always shown, now keyed by
 * the row's `uid` so they stay with the right activity when the list is sorted.
 */
function useConflicts(
    enabled: boolean,
    entries: { key: string; fields: ActivityFields }[],
): Record<string, ConflictResult> {
    const [results, setResults] = useState<Record<string, ConflictResult>>({});
    const signature = JSON.stringify(
        entries.map((e) => [
            e.key,
            e.fields.venue,
            e.fields.activity_date,
            e.fields.start_time,
            e.fields.end_time,
        ]),
    );
    const entriesRef = useRef(entries);
    useEffect(() => {
        entriesRef.current = entries;
    });

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const checkable = entriesRef.current.filter(
            ({ fields: f }) =>
                f.venue && f.activity_date && f.start_time && f.end_time,
        );

        if (checkable.length === 0) {
            setResults({});

            return;
        }

        let cancelled = false;
        const timer = setTimeout(() => {
            fetch('/activity-calendars/conflict-check', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    activities: checkable.map(({ fields: f }) => ({
                        venue: f.venue,
                        activity_date: f.activity_date,
                        start_time: f.start_time,
                        end_time: f.end_time,
                    })),
                }),
            })
                .then((res) => res.json())
                .then((data) => {
                    if (cancelled) {
                        return;
                    }

                    const next: Record<string, ConflictResult> = {};
                    (data.results ?? []).forEach(
                        (result: ConflictResult, i: number) => {
                            next[checkable[i].key] = result;
                        },
                    );
                    setResults(next);
                })
                .catch(() => {});
        }, 600);

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [enabled, signature]);

    return results;
}

function ConflictNotices({ result }: { result: ConflictResult | undefined }) {
    if (!result) {
        return null;
    }

    const names = (list: ConflictEntry[]) =>
        list.map((c) => `"${c.name}" (${c.organization})`).join(', ');

    return (
        <>
            {result.confirmed.length > 0 && (
                <PageNotice
                    tone="destructive"
                    urgent
                    title="Confirmed conflict with an approved booking."
                >
                    {names(result.confirmed)}
                </PageNotice>
            )}
            {result.tentative.length > 0 && (
                <PageNotice
                    tone="warning"
                    title="Tentative overlap with a pending booking."
                >
                    {names(result.tentative)}. This is a warning only. You can
                    still submit.
                </PageNotice>
            )}
        </>
    );
}

/** How a row behaves on a page that locks some of them (Fix and Resend). */
export type RowAccess = {
    /** False: a plain read-only row, no pencil, no delete. */
    editable: boolean;
    /** Sits at the end of the name line, e.g. a "Needs a change" badge. */
    badge?: ReactNode;
    /** Under the row, e.g. the approver's comment. */
    note?: ReactNode;
    /** DOM id of the row, so a "jump to" link can find it. */
    anchorId?: string;
};

type Props = {
    rows: EditorRow[];
    onRowsChange: (updater: (prev: EditorRow[]) => EditorRow[]) => void;
    sdgs: SdgOption[];
    /** Server errors, keyed `activities.{index in rows}.{field}`. */
    errors: Record<string, string>;
    minDate?: string;
    /** Page-specific behaviour per row; defaults to every row editable. */
    rowAccess?: (row: EditorRow) => RowAccess;
    /** Adding and deleting are off while the server holds the row set fixed. */
    canAddAndDelete?: boolean;
    /** Live venue-conflict warnings (create page only). */
    checkConflicts?: boolean;
    /** True while the form holds typed-but-unadded or unsaved changes. */
    onPendingChange?: (pending: boolean) => void;
    idPrefix: string;
};

/**
 * The activity list plus the one form that adds to it or edits one of its
 * rows. All of it is client state; the page owns the rows and sends them.
 */
export default function ActivityCalendarEditor({
    rows,
    onRowsChange,
    sdgs,
    errors,
    minDate,
    rowAccess,
    canAddAndDelete = true,
    checkConflicts = false,
    onPendingChange,
    idPrefix,
}: Props) {
    const [editingUid, setEditingUid] = useState<string | null>(null);
    const [form, setForm] = useState<ActivityFields>(emptyFields);
    const [formErrors, setFormErrors] = useState<
        Partial<Record<keyof ActivityFields, string>>
    >({});
    const [pendingAction, setPendingAction] = useState<
        null | { kind: 'edit'; uid: string } | { kind: 'reset' }
    >(null);
    const [deleteUid, setDeleteUid] = useState<string | null>(null);
    const formRef = useRef<HTMLElement>(null);

    const editingRow = rows.find((r) => r.uid === editingUid) ?? null;
    const baseline = editingRow ? toActivityFields(editingRow) : emptyFields();
    const dirty = !sameFields(form, baseline);

    useEffect(() => {
        onPendingChange?.(dirty);
    }, [dirty, onPendingChange]);

    const indexOf = useCallback(
        (uid: string) => rows.findIndex((r) => r.uid === uid),
        [rows],
    );

    const sorted = useMemo(
        () =>
            rows
                .map((row, index) => ({ row, index }))
                .sort(
                    (a, b) =>
                        a.row.activity_date.localeCompare(
                            b.row.activity_date,
                        ) ||
                        a.row.start_time.localeCompare(b.row.start_time) ||
                        a.index - b.index,
                ),
        [rows],
    );

    const total = rows.reduce((sum, r) => sum + (Number(r.budget) || 0), 0);

    const conflictEntries = useMemo(() => {
        const list = rows.map((r) => ({
            key: r.uid,
            fields: r.uid === editingUid ? form : toActivityFields(r),
        }));

        if (!editingUid) {
            list.push({ key: 'form', fields: form });
        }

        return list;
    }, [rows, editingUid, form]);
    const conflicts = useConflicts(checkConflicts, conflictEntries);

    function setField<K extends keyof ActivityFields>(
        key: K,
        value: ActivityFields[K],
    ) {
        setForm((prev) => ({ ...prev, [key]: value }));
        setFormErrors((prev) => ({ ...prev, [key]: undefined }));
    }

    function loadForEdit(uid: string) {
        const row = rows.find((r) => r.uid === uid);

        if (!row) {
            return;
        }

        setEditingUid(uid);
        setForm(toActivityFields(row));
        setFormErrors({});
        formRef.current?.scrollIntoView?.({
            behavior: 'smooth',
            block: 'start',
        });
    }

    function resetForm() {
        setEditingUid(null);
        setForm(emptyFields());
        setFormErrors({});
    }

    function requestEdit(uid: string) {
        if (uid === editingUid) {
            formRef.current?.scrollIntoView?.({ behavior: 'smooth' });

            return;
        }

        if (dirty) {
            setPendingAction({ kind: 'edit', uid });

            return;
        }

        loadForEdit(uid);
    }

    function requestReset() {
        if (dirty) {
            setPendingAction({ kind: 'reset' });

            return;
        }

        resetForm();
    }

    function commit() {
        const found = validateFields(form, minDate);

        if (Object.keys(found).length > 0) {
            setFormErrors(found);
            const first = Object.keys(found)[0];
            window.setTimeout(() => {
                (
                    formRef.current?.querySelector(
                        `[data-field="${first}"]`,
                    ) as HTMLElement | null
                )?.focus();
            }, 0);

            return;
        }

        if (editingUid) {
            onRowsChange((prev) =>
                prev.map((r) => (r.uid === editingUid ? { ...r, ...form } : r)),
            );
            notify.success({
                title: 'Activity updated',
                message: `${form.name} is updated in the list.`,
            });
        } else {
            onRowsChange((prev) => [
                ...prev,
                { ...form, uid: newUid(), position: null },
            ]);
            notify.success({
                title: 'Activity added',
                message: `${form.name} is in the list. Submit when you’re done.`,
            });
        }

        resetForm();
    }

    function confirmDelete() {
        const uid = deleteUid;
        const row = rows.find((r) => r.uid === uid);

        if (!uid || !row) {
            return;
        }

        onRowsChange((prev) => prev.filter((r) => r.uid !== uid));

        if (uid === editingUid) {
            resetForm();
        }

        setDeleteUid(null);
        notify.success({
            title: 'Activity removed',
            message: `${row.name} is no longer in the list.`,
        });
    }

    const mode = editingRow ? 'edit' : 'add';
    const id = (field: string) => `${idPrefix}-${field}`;
    const err = (field: keyof ActivityFields) =>
        formErrors[field] ??
        (editingRow
            ? errors[`activities.${indexOf(editingRow.uid)}.${field}`]
            : undefined);
    const deleteRow = rows.find((r) => r.uid === deleteUid);

    return (
        <>
            <section
                aria-labelledby={id('list-title')}
                className="space-y-3 border-b px-5 py-5 sm:px-6"
            >
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <h2
                        id={id('list-title')}
                        className="text-base font-semibold"
                    >
                        Activities
                    </h2>
                    {rows.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary" className="tabular-nums">
                                {rows.length}{' '}
                                {rows.length === 1 ? 'activity' : 'activities'}
                            </Badge>
                            <Badge variant="secondary" className="tabular-nums">
                                {formatPeso(total)} total
                            </Badge>
                        </div>
                    )}
                </div>

                {rows.length === 0 ? (
                    <Empty className="border border-dashed">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Calendar />
                            </EmptyMedia>
                            <EmptyTitle>No activities yet</EmptyTitle>
                            <EmptyDescription>
                                Fill in the form below and choose “Add to list”.
                                Your calendar needs at least one activity before
                                you can submit it.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul
                        className="divide-y overflow-hidden rounded-lg border"
                        aria-label="Activities in this calendar"
                    >
                        {sorted.map(({ row, index }) => {
                            const access = rowAccess?.(row) ?? {
                                editable: true,
                            };
                            const editing = row.uid === editingUid;
                            const sdgBadges = row.sdg
                                .map((value) =>
                                    sdgs.find((s) => s.value === value),
                                )
                                .filter((s): s is SdgOption => Boolean(s))
                                .map(sdgParts);
                            const rowErrors = Object.entries(errors)
                                .filter(
                                    ([key]) =>
                                        key === `activities.${index}` ||
                                        key.startsWith(`activities.${index}.`),
                                )
                                .map(([, message]) => message);

                            return (
                                <li
                                    key={row.uid}
                                    id={access.anchorId}
                                    aria-current={editing ? 'true' : undefined}
                                    className={cn(
                                        'scroll-mt-24 border-l-2 border-transparent',
                                        editing &&
                                            'border-l-primary bg-primary/5',
                                    )}
                                >
                                    <div className="grid grid-cols-[auto_minmax(0,1fr)] items-start gap-x-3 gap-y-3 px-4 py-3 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">
                                        {row.activity_date ? (
                                            <DateBadge
                                                iso={row.activity_date}
                                            />
                                        ) : (
                                            <div className="size-12" />
                                        )}

                                        <div className="min-w-0 space-y-1.5">
                                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <p className="min-w-0 text-sm font-semibold break-words">
                                                    {row.name}
                                                </p>
                                                {editing && (
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            BRAND_TINT_OUTLINE
                                                        }
                                                    >
                                                        Editing
                                                    </Badge>
                                                )}
                                                {access.badge}
                                            </div>
                                            <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                                <span className="flex items-center gap-1">
                                                    <Clock
                                                        aria-hidden
                                                        className="size-3.5"
                                                    />
                                                    <span className="sr-only">
                                                        Time:{' '}
                                                    </span>
                                                    {formatClock(
                                                        row.start_time,
                                                    )}{' '}
                                                    to{' '}
                                                    {formatClock(row.end_time)}
                                                </span>
                                                <span className="flex items-center gap-1">
                                                    <MapPin
                                                        aria-hidden
                                                        className="size-3.5"
                                                    />
                                                    <span className="sr-only">
                                                        Venue:{' '}
                                                    </span>
                                                    {row.venue}
                                                </span>
                                                <span className="flex items-center gap-1">
                                                    <User
                                                        aria-hidden
                                                        className="size-3.5"
                                                    />
                                                    <span className="sr-only">
                                                        Participants:{' '}
                                                    </span>
                                                    {
                                                        row.participant_program_assigned
                                                    }
                                                </span>
                                            </p>
                                            {sdgBadges.length > 0 && (
                                                <ul
                                                    aria-label="Target SDGs"
                                                    className="flex flex-wrap gap-1.5"
                                                >
                                                    {sdgBadges.map((s) => (
                                                        <li key={s.number}>
                                                            <Badge
                                                                variant="outline"
                                                                title={`SDG ${s.number}: ${s.full}`}
                                                                className={
                                                                    BRAND_TINT_OUTLINE
                                                                }
                                                            >
                                                                SDG {s.number}
                                                            </Badge>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>

                                        <div className="col-span-2 flex items-center justify-between gap-3 sm:col-span-1 sm:justify-end">
                                            <p className="text-sm font-semibold tabular-nums">
                                                {formatPeso(row.budget)}
                                            </p>
                                            {access.editable ? (
                                                <div className="flex items-center gap-1.5">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        aria-label={`Edit ${row.name}`}
                                                        title="Edit"
                                                        onClick={() =>
                                                            requestEdit(row.uid)
                                                        }
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                    {canAddAndDelete && (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            aria-label={`Delete ${row.name}`}
                                                            title="Delete"
                                                            onClick={() =>
                                                                setDeleteUid(
                                                                    row.uid,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    Locked
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    {(access.note ||
                                        rowErrors.length > 0 ||
                                        conflicts[row.uid]) && (
                                        <div className="space-y-2 px-4 pb-3">
                                            {access.note}
                                            {rowErrors.length > 0 && (
                                                <PageNotice
                                                    tone="destructive"
                                                    urgent
                                                    title="This activity needs a change."
                                                >
                                                    {rowErrors.join(' ')}
                                                </PageNotice>
                                            )}
                                            <ConflictNotices
                                                result={conflicts[row.uid]}
                                            />
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}

                {rows.length > 0 && (
                    <p className="text-xs text-muted-foreground">
                        Sorted by date. Click the pencil to change an activity,
                        it opens in the form below.
                    </p>
                )}
            </section>

            {!canAddAndDelete && !editingRow ? (
                <p className="px-5 py-5 text-sm text-muted-foreground sm:px-6">
                    Only the activities marked “Needs a change” can be edited.
                    Choose the pencil on one to open it here.
                </p>
            ) : (
                <section
                    ref={formRef}
                    aria-labelledby={id('form-title')}
                    className="scroll-mt-24 space-y-4 px-5 py-5 sm:px-6"
                >
                    <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <h2
                            id={id('form-title')}
                            className="text-base font-semibold"
                        >
                            {mode === 'edit'
                                ? 'Edit activity'
                                : 'Add an activity'}
                        </h2>
                        <p className="min-w-0 text-xs break-words text-muted-foreground">
                            {mode === 'edit'
                                ? editingRow?.name
                                : `Activity ${rows.length + 1}`}
                        </p>
                    </div>

                    {!editingUid && <ConflictNotices result={conflicts.form} />}
                    {editingUid && (
                        <ConflictNotices result={conflicts[editingUid]} />
                    )}

                    <FormField
                        id={id('name')}
                        label="Activity name"
                        error={err('name')}
                    >
                        {(aria) => (
                            <Input
                                id={id('name')}
                                data-field="name"
                                value={form.name}
                                placeholder="e.g. Leadership Summit"
                                onChange={(e) =>
                                    setField('name', e.target.value)
                                }
                                {...aria}
                            />
                        )}
                    </FormField>

                    <FormField
                        id={id('venue')}
                        label="Venue"
                        helper="Type the exact venue name. Check the venue calendar to make sure it’s free."
                        error={err('venue')}
                    >
                        {(aria) => (
                            <div className="relative">
                                <MapPin
                                    aria-hidden
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id={id('venue')}
                                    data-field="venue"
                                    value={form.venue}
                                    className="pl-9"
                                    onChange={(e) =>
                                        setField('venue', e.target.value)
                                    }
                                    {...aria}
                                />
                            </div>
                        )}
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)]">
                        <FormField
                            id={id('date')}
                            label="Date"
                            error={err('activity_date')}
                        >
                            {(aria) => (
                                <div className="relative">
                                    <Calendar
                                        aria-hidden
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        id={id('date')}
                                        data-field="activity_date"
                                        type="date"
                                        min={minDate}
                                        value={form.activity_date}
                                        className="pl-9"
                                        onChange={(e) =>
                                            setField(
                                                'activity_date',
                                                e.target.value,
                                            )
                                        }
                                        {...aria}
                                    />
                                </div>
                            )}
                        </FormField>
                        <FormField
                            id={id('start')}
                            label="Start time"
                            error={err('start_time')}
                        >
                            {(aria) => (
                                <div className="relative">
                                    <Clock
                                        aria-hidden
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        id={id('start')}
                                        data-field="start_time"
                                        type="time"
                                        value={form.start_time}
                                        className="pl-9"
                                        onChange={(e) =>
                                            setField(
                                                'start_time',
                                                e.target.value,
                                            )
                                        }
                                        {...aria}
                                    />
                                </div>
                            )}
                        </FormField>
                        <FormField
                            id={id('end')}
                            label="End time"
                            error={err('end_time')}
                        >
                            {(aria) => (
                                <div className="relative">
                                    <Clock
                                        aria-hidden
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        id={id('end')}
                                        data-field="end_time"
                                        type="time"
                                        value={form.end_time}
                                        className="pl-9"
                                        onChange={(e) =>
                                            setField('end_time', e.target.value)
                                        }
                                        {...aria}
                                    />
                                </div>
                            )}
                        </FormField>
                    </div>

                    <fieldset
                        className="grid gap-2"
                        data-field="sdg"
                        tabIndex={-1}
                    >
                        <legend className="mb-1.5 text-sm leading-snug font-medium">
                            Target SDGs{' '}
                            <span className="ml-1 text-xs font-normal text-muted-foreground">
                                pick at least one
                            </span>
                        </legend>
                        <div className="flex flex-wrap gap-2">
                            {sdgs.map((option) => {
                                const { number, short, full } =
                                    sdgParts(option);
                                const on = form.sdg.includes(option.value);

                                return (
                                    <Toggle
                                        key={option.value}
                                        variant="outline"
                                        size="sm"
                                        pressed={on}
                                        title={`SDG ${number}: ${full}`}
                                        aria-label={`SDG ${number}: ${full}`}
                                        onPressedChange={(next) =>
                                            setField(
                                                'sdg',
                                                next
                                                    ? [
                                                          ...form.sdg,
                                                          option.value,
                                                      ]
                                                    : form.sdg.filter(
                                                          (v) =>
                                                              v !==
                                                              option.value,
                                                      ),
                                            )
                                        }
                                        className={cn(
                                            'h-8 rounded-full px-3 text-xs font-normal',
                                            'data-[state=on]:border-brand-soft-border data-[state=on]:bg-brand-soft data-[state=on]:text-brand-soft-foreground data-[state=on]:hover:bg-brand-soft',
                                        )}
                                    >
                                        <span
                                            aria-hidden
                                            className="tabular-nums opacity-70"
                                        >
                                            {number}
                                        </span>
                                        <span aria-hidden>{short}</span>
                                    </Toggle>
                                );
                            })}
                        </div>
                        {err('sdg') && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {err('sdg')}
                            </p>
                        )}
                    </fieldset>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id={id('participants')}
                            label="Participants or program"
                            error={err('participant_program_assigned')}
                        >
                            {(aria) => (
                                <Input
                                    id={id('participants')}
                                    data-field="participant_program_assigned"
                                    value={form.participant_program_assigned}
                                    placeholder="e.g. BSCS, all year levels"
                                    onChange={(e) =>
                                        setField(
                                            'participant_program_assigned',
                                            e.target.value,
                                        )
                                    }
                                    {...aria}
                                />
                            )}
                        </FormField>
                        <FormField
                            id={id('budget')}
                            label="Budget"
                            error={err('budget')}
                        >
                            {(aria) => (
                                <AddonInput
                                    id={id('budget')}
                                    data-field="budget"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    prefix="₱"
                                    value={form.budget}
                                    invalid={Boolean(err('budget'))}
                                    onChange={(e) =>
                                        setField('budget', e.target.value)
                                    }
                                    aria-describedby={aria['aria-describedby']}
                                />
                            )}
                        </FormField>
                    </div>

                    <FormField
                        id={id('description')}
                        label="Description"
                        optional
                        error={err('description')}
                    >
                        {(aria) => (
                            <Textarea
                                id={id('description')}
                                data-field="description"
                                rows={3}
                                value={form.description}
                                placeholder="A short note about the activity"
                                onChange={(e) =>
                                    setField('description', e.target.value)
                                }
                                {...aria}
                            />
                        )}
                    </FormField>

                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={requestReset}
                        >
                            {mode === 'edit' ? 'Cancel' : 'Clear'}
                        </Button>
                        <Button type="button" onClick={commit}>
                            {mode === 'edit' ? (
                                'Save changes'
                            ) : (
                                <>
                                    <Plus data-icon="inline-start" />
                                    Add to list
                                </>
                            )}
                        </Button>
                    </div>
                </section>
            )}

            <ConfirmDialog
                open={pendingAction !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingAction(null);
                    }
                }}
                title="Discard your changes?"
                description={
                    mode === 'edit'
                        ? `You have unsaved changes to ${editingRow?.name}. They’ll be lost if you continue.`
                        : 'What you typed in the form hasn’t been added to the list yet. It’ll be lost if you continue.'
                }
                confirmLabel="Discard changes"
                confirmVariant="destructive"
                onConfirm={({ close }) => {
                    const action = pendingAction;
                    close();
                    setPendingAction(null);

                    if (action?.kind === 'edit') {
                        loadForEdit(action.uid);
                    } else {
                        resetForm();
                    }
                }}
            />

            <ConfirmDialog
                open={deleteUid !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteUid(null);
                    }
                }}
                title="Remove this activity?"
                description={
                    deleteRow
                        ? `“${deleteRow.name}” will be taken off your calendar. You can add it again before you submit.`
                        : ''
                }
                confirmLabel="Remove activity"
                confirmVariant="destructive"
                onConfirm={({ close }) => {
                    confirmDelete();
                    close();
                }}
            />
        </>
    );
}
