import {
    ArrowDown,
    Check,
    FileText,
    Lock,
    MessageCircle,
    Send,
    Undo2,
    Upload,
} from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type { ChangeEvent, ReactNode } from 'react';
import { AttachmentPreview } from '@/components/attachment-preview';
import type {
    AttachmentSlotDef,
    ExistingAttachment,
} from '@/components/attachment-slot-field';
import { formatFileSize } from '@/components/document-view/format';
import { FormFooter, FormShell, FormStrip } from '@/components/form-shell';
import FormSubmitConfirm from '@/components/form-submit-confirm';
import PageNotice from '@/components/page-notice';
import { ToneBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { needText, progressText } from '@/lib/fix-resend';
import type { FixDraftValues, FixItem, FixProps } from '@/lib/fix-resend';
import { xsrfToken } from '@/lib/notifications';
import { notify } from '@/lib/toast';
import { cn } from '@/lib/utils';

type FixState = {
    fix: FixProps;
    /** Per flagged item: has it been fixed yet? Items report themselves as they change. */
    fixed: Record<string, boolean>;
    report: (key: string, fixed: boolean) => void;
    isFlagged: (key: string) => boolean;
    fixedCount: number;
    total: number;
    allFixed: boolean;
};

const FixContext = createContext<FixState | null>(null);

/** Holds which flagged items are fixed, so the summary chips, each item and the footer agree. */
export function FixProvider({
    fix,
    children,
}: {
    fix: FixProps;
    children: ReactNode;
}) {
    const [fixed, setFixed] = useState<Record<string, boolean>>({});

    const report = useCallback((key: string, value: boolean) => {
        setFixed((prev) =>
            prev[key] === value ? prev : { ...prev, [key]: value },
        );
    }, []);

    const state = useMemo<FixState>(() => {
        const keys = fix.items.map((item) => item.key);
        const fixedCount = keys.filter((key) => fixed[key]).length;

        return {
            fix,
            fixed,
            report,
            isFlagged: (key) => fix.active && keys.includes(key),
            fixedCount,
            total: keys.length,
            allFixed: !fix.active || fixedCount === keys.length,
        };
    }, [fix, fixed, report]);

    return <FixContext.Provider value={state}>{children}</FixContext.Provider>;
}

export function useFix(): FixState {
    const state = useContext(FixContext);

    if (!state) {
        throw new Error('useFix must be used inside <FixProvider>');
    }

    return state;
}

/** "Oct 9, 2026" */
function shortDate(iso: string | null): string | null {
    return iso
        ? new Date(iso).toLocaleDateString('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              timeZone: 'Asia/Manila',
          })
        : null;
}

/** The top strip: which document, and when it came back. */
export function FixStrip({ organization }: { organization?: string | null }) {
    const { fix } = useFix();
    const returned = shortDate(fix.returnedAt);

    return (
        <FormStrip
            left={
                <>
                    {fix.formLabel} for{' '}
                    <strong className="font-semibold text-foreground">
                        {organization ?? fix.organization ?? ''}
                    </strong>
                </>
            }
            right={returned ? `Returned ${returned}` : undefined}
        />
    );
}

function scrollToItem(key: string) {
    const target = document.getElementById(`fix-item-${key}`);

    target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target
        ?.querySelector<HTMLElement>(
            'input:not([type=file]), textarea, select, button',
        )
        ?.focus({ preventScroll: true });
}

/**
 * The amber block under the strip: how many items need changes, who returned
 * it, and one chip per item that jumps to it. A chip turns green with a check
 * once its item is fixed. When the return flagged nothing fixable (no flags, or
 * only "General"), it is the plain return note instead and the form is open.
 */
export function FixSummary() {
    const { fix, fixed, fixedCount, total } = useFix();
    const by = fix.returnedBy
        ? `Returned by ${fix.returnedBy.name ? `${fix.returnedBy.name}, ` : ''}${fix.returnedBy.role}`
        : `Returned by ${fix.role}`;

    if (!fix.active) {
        return (
            <div className="border-b px-5 py-4 sm:px-6">
                <PageNotice tone="warning" title={by}>
                    {fix.comment && <p>{fix.comment}</p>}
                    {fix.notes.map((note) => (
                        <p key={note.label} className="mt-1">
                            <span className="font-medium">{note.label}:</span>{' '}
                            {note.comment ?? 'Flagged for changes.'}
                        </p>
                    ))}
                    <p className="mt-1">
                        Nothing here was singled out, so the whole form is open
                        for changes.
                    </p>
                </PageNotice>
            </div>
        );
    }

    return (
        <section
            aria-labelledby="fix-summary-title"
            className="flex gap-3 border-b bg-warning/10 px-5 py-4 sm:px-6"
        >
            <span
                aria-hidden
                className="flex size-9 shrink-0 items-center justify-center rounded-md border border-warning/40 bg-warning/15 text-warning-foreground"
            >
                <Undo2 className="size-4" />
            </span>
            <div className="min-w-0 space-y-2">
                <div>
                    <h2
                        id="fix-summary-title"
                        className="text-sm font-semibold"
                    >
                        {needText(total)}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {by}
                        <span className="sr-only">
                            . {fixedCount} of {total} fixed so far.
                        </span>
                    </p>
                </div>
                <ul className="flex flex-wrap gap-2" aria-label="Items to fix">
                    {fix.items.map((item) => {
                        const done = fixed[item.key] === true;

                        return (
                            <li key={item.key}>
                                <a
                                    href={`#fix-item-${item.key}`}
                                    onClick={(event) => {
                                        event.preventDefault();
                                        scrollToItem(item.key);
                                    }}
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium focus-visible:focus-ring',
                                        done
                                            ? 'border-success/40 bg-success/10 text-success-foreground'
                                            : 'border-warning/50 bg-warning/10 text-warning-foreground hover:bg-warning/20',
                                    )}
                                >
                                    {done ? (
                                        <Check
                                            aria-hidden
                                            className="size-3.5"
                                            strokeWidth={3}
                                        />
                                    ) : null}
                                    {item.label}
                                    {done ? (
                                        <span className="sr-only">
                                            {' '}
                                            (fixed)
                                        </span>
                                    ) : (
                                        <ArrowDown
                                            aria-hidden
                                            className="size-3.5"
                                        />
                                    )}
                                </a>
                            </li>
                        );
                    })}
                </ul>
                {fix.notes.length > 0 && (
                    <ul className="space-y-0.5 text-xs text-muted-foreground">
                        {fix.notes.map((note) => (
                            <li key={note.label}>
                                <span className="font-medium text-foreground">
                                    {note.label}:
                                </span>{' '}
                                {note.comment ?? 'Flagged for changes.'}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}

/**
 * One section of the page. Its header says whether anything in it can be
 * changed: "Locked" when nothing is flagged here, "n to fix" when something is.
 */
export function FixSection({
    title,
    itemKeys,
    lockedLabel,
    children,
}: {
    title: string;
    /** Keys of the flagged items this section can hold; none flagged means it is locked. */
    itemKeys: string[];
    /** Replaces the badge with a count, e.g. "7 of 9 locked", for a section that is only partly locked. */
    lockedLabel?: string;
    children: ReactNode;
}) {
    const { fix, isFlagged } = useFix();
    const flagged = itemKeys.filter(isFlagged).length;

    return (
        <section
            aria-label={title}
            className="space-y-4 border-b px-5 py-5 last:border-b-0 sm:px-6"
        >
            <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <h2 className="text-base font-semibold">{title}</h2>
                {fix.active && lockedLabel ? (
                    <ToneBadge tone="neutral" className="gap-1 normal-case">
                        <Lock aria-hidden /> {lockedLabel}
                    </ToneBadge>
                ) : (
                    fix.active &&
                    (flagged === 0 ? (
                        <ToneBadge tone="neutral" className="gap-1 normal-case">
                            <Lock aria-hidden /> Locked
                        </ToneBadge>
                    ) : (
                        <ToneBadge tone="warning" className="normal-case">
                            {flagged} to fix
                        </ToneBadge>
                    ))
                )}
            </div>
            <div className="space-y-4">{children}</div>
        </section>
    );
}

/** A small label with its value under it, for the read-only parts of a locked section. */
export function LockedField({
    label,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div className="min-w-0 space-y-0.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm break-words">
                {value === null || value === '' ? '—' : value}
            </dd>
        </div>
    );
}

export function LockedGrid({
    children,
    columns = 2,
}: {
    children: ReactNode;
    columns?: 1 | 2;
}) {
    return (
        <dl
            className={cn(
                'grid gap-x-8 gap-y-3',
                columns === 2 && 'sm:grid-cols-2',
            )}
        >
            {children}
        </dl>
    );
}

/** The comment line at the top of a flagged item: "SDAO: Please describe...". */
function ItemComment({ item }: { item: FixItem }) {
    const { fix } = useFix();

    if (!item.comment) {
        return null;
    }

    return (
        <p className="flex items-start gap-2 border-b border-warning/30 px-3.5 py-2.5 text-sm text-warning-foreground">
            <MessageCircle aria-hidden className="mt-0.5 size-4 shrink-0" />
            <span>
                <strong className="font-semibold">{fix.role}:</strong>{' '}
                {item.comment}
            </span>
        </p>
    );
}

/**
 * One flagged item, in whatever state the page is in:
 *  - the return flagged it: an amber box with the approver's comment on top and
 *    the editable `children` under it, with a green CHANGED badge once `changed`;
 *  - the return did not flag it: the read-only `locked` view, no inputs at all;
 *  - nothing on this return is fixable: the plain `children`, as before.
 */
export function FixItemBox({
    sectionKey,
    changed,
    locked,
    heading,
    badges,
    children,
}: {
    sectionKey: string;
    /** Whether the officer has changed this item from the returned version. */
    changed: boolean;
    /** What to show instead when this item is locked. */
    locked: ReactNode;
    /** Overrides the item's label as the box's heading. */
    heading?: string;
    /** Replaces the CHANGED badge (a file shows NEW FILE ADDED / NEEDS NEW FILE instead). */
    badges?: ReactNode;
    children: ReactNode;
}) {
    const { fix, report } = useFix();
    const item = fix.items.find((candidate) => candidate.key === sectionKey);

    useEffect(() => {
        if (item) {
            report(sectionKey, changed);
        }
    }, [item, sectionKey, changed, report]);

    if (!fix.active) {
        return <>{children}</>;
    }

    if (!item) {
        return <>{locked}</>;
    }

    return (
        <div
            id={`fix-item-${sectionKey}`}
            role="group"
            aria-label={`${heading ?? item.label}, needs your changes`}
            className="scroll-mt-24 overflow-hidden rounded-lg border border-warning/50 bg-warning/5"
        >
            <ItemComment item={item} />
            <div className="space-y-3 px-3.5 py-3">
                <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-sm font-semibold">
                        {heading ?? item.label}
                    </h3>
                    {badges ??
                        (changed ? (
                            <ToneBadge tone="success">Changed</ToneBadge>
                        ) : (
                            <ToneBadge tone="warning">Needs a change</ToneBadge>
                        ))}
                </div>
                {children}
            </div>
        </div>
    );
}

/**
 * A flagged attachment slot: the old file (struck through while it can still
 * be named), then a picker. The new file is chosen here and travels with the
 * resend; the old one is only replaced when the resend succeeds, so its name
 * is still on the page the whole time.
 */
export function FlaggedFile({
    slot,
    existing,
    files,
    onFilesChange,
    error,
    native = true,
}: {
    slot: AttachmentSlotDef;
    existing: ExistingAttachment[];
    /** The files chosen in this window for this slot (always one, at most, for a single-file slot). */
    files: File[];
    onFilesChange: (files: File[]) => void;
    error?: string;
    native?: boolean;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [localError, setLocalError] = useState<string | null>(null);
    const shownError = localError ?? error;
    const maxMb = slot.max_kb / 1024;
    const maxLabel = `${Number.isInteger(maxMb) ? maxMb : maxMb.toFixed(1)} MB`;
    const extensions = slot.accept
        .split(',')
        .map((part) => part.trim().replace(/^\./, '').toLowerCase())
        .filter(Boolean);
    const chosen = files.length > 0;

    function handleChange(event: ChangeEvent<HTMLInputElement>) {
        const picked = Array.from(event.target.files ?? []);

        if (picked.length === 0) {
            return;
        }

        const refused = picked.find(
            (candidate) =>
                candidate.size > slot.max_kb * 1024 ||
                (extensions.length > 0 &&
                    !extensions.includes(
                        candidate.name.split('.').pop()?.toLowerCase() ?? '',
                    )),
        );

        if (refused) {
            setLocalError(
                refused.size > slot.max_kb * 1024
                    ? `"${refused.name}" is too large. This requirement accepts files up to ${maxLabel}.`
                    : `"${refused.name}" is not an accepted file type. Use ${extensions.join(', ').toUpperCase()}.`,
            );
            event.target.value = '';
            onFilesChange([]);

            return;
        }

        setLocalError(null);
        onFilesChange(picked);
    }

    function remove() {
        if (inputRef.current) {
            inputRef.current.value = '';
        }

        setLocalError(null);
        onFilesChange([]);
    }

    return (
        <div className="space-y-2">
            {existing.length > 0 && (
                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <FileText aria-hidden className="size-3.5 shrink-0" />
                    <span>
                        {slot.multiple ? 'Already on file:' : 'Old file:'}
                    </span>
                    <span
                        className={cn(
                            'truncate',
                            chosen && !slot.multiple && 'line-through',
                        )}
                    >
                        {existing.map((f) => f.original_filename).join(', ')}
                    </span>
                </p>
            )}

            <input
                ref={inputRef}
                id={`attachment-${slot.key}`}
                type="file"
                name={
                    native
                        ? slot.multiple
                            ? `attachments[${slot.key}][]`
                            : `attachments[${slot.key}]`
                        : undefined
                }
                accept={slot.accept}
                multiple={slot.multiple}
                onChange={handleChange}
                className="sr-only"
                tabIndex={-1}
                aria-hidden
            />

            {chosen ? (
                <div className="flex items-center gap-3 rounded-md border border-success/40 bg-success/10 px-3 py-2.5">
                    <span
                        aria-hidden
                        className="flex size-8 shrink-0 items-center justify-center rounded-md bg-success text-white"
                    >
                        <Check className="size-4" strokeWidth={3} />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">
                            {files.length === 1
                                ? files[0].name
                                : `${files.length} files chosen`}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Ready to send ·{' '}
                            {formatFileSize(
                                files.reduce(
                                    (total, file) => total + file.size,
                                    0,
                                ),
                            )}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={remove}
                        aria-label={`Remove the new ${slot.multiple ? 'files' : 'file'} for ${slot.label}`}
                    >
                        Remove
                    </Button>
                </div>
            ) : (
                <div className="flex items-center gap-3 rounded-md border border-dashed border-warning/60 px-3 py-2.5">
                    <span
                        aria-hidden
                        className="flex size-8 shrink-0 items-center justify-center rounded-md bg-warning/15 text-warning-foreground"
                    >
                        <Upload className="size-4" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium">
                            {slot.multiple
                                ? 'Upload the new files'
                                : 'Upload the new file'}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {extensions.length > 0
                                ? `${extensions.join(', ').toUpperCase()}, `
                                : ''}
                            up to {maxLabel}
                            {slot.multiple ? ' each' : ''}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => inputRef.current?.click()}
                        aria-label={`Choose ${slot.multiple ? 'new files' : 'a new file'} for ${slot.label}`}
                        aria-invalid={shownError ? true : undefined}
                        aria-describedby={
                            shownError ? `${slot.key}-fix-error` : undefined
                        }
                    >
                        Choose file{slot.multiple ? 's' : ''}
                    </Button>
                </div>
            )}
            {shownError && (
                <p
                    id={`${slot.key}-fix-error`}
                    className="text-sm text-destructive"
                >
                    {shownError}
                </p>
            )}
        </div>
    );
}

/** The badges of a flagged file: NEW FILE ADDED once chosen, NEEDS NEW FILE until then. */
export function fileBadges(chosen: boolean): ReactNode {
    return chosen ? (
        <ToneBadge tone="success">New file added</ToneBadge>
    ) : (
        <ToneBadge tone="warning">Needs new file</ToneBadge>
    );
}

/** The requirements that were not flagged: a quiet locked list, each with a View link. */
export function LockedRequirements({
    slots,
    existing,
}: {
    slots: AttachmentSlotDef[];
    existing: Record<string, ExistingAttachment[]>;
}) {
    if (slots.length === 0) {
        return null;
    }

    return (
        <ul className="divide-y overflow-hidden rounded-lg border">
            {slots.map((slot) => {
                const files = existing[slot.key] ?? [];

                return (
                    <li
                        key={slot.key}
                        className="flex flex-wrap items-center gap-x-3 gap-y-1 px-3.5 py-2.5 text-sm"
                    >
                        <Lock
                            aria-hidden
                            className="size-3.5 shrink-0 text-muted-foreground"
                        />
                        <span className="min-w-0 flex-1 basis-40 font-medium">
                            {slot.label}
                            <span className="sr-only"> (locked)</span>
                        </span>
                        {files.length === 0 ? (
                            <span className="text-xs text-muted-foreground">
                                No file
                            </span>
                        ) : (
                            files.map((file) => (
                                <span
                                    key={file.id}
                                    className="flex min-w-0 items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span className="truncate">
                                        {file.original_filename}
                                    </span>
                                    <AttachmentPreview
                                        file={file}
                                        label={slot.label}
                                    >
                                        <button
                                            type="button"
                                            aria-label={`View ${file.original_filename}`}
                                            className="font-medium text-primary-text underline underline-offset-4 focus-visible:focus-ring-edge"
                                        >
                                            View
                                        </button>
                                    </AttachmentPreview>
                                </span>
                            ))
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

/** "3 of 9 locked" for a requirements header. */
export function lockedSummary(
    slots: AttachmentSlotDef[],
    flaggedKeys: string[],
): string {
    const locked = slots.filter(
        (slot) => !flaggedKeys.includes(slot.key),
    ).length;

    return `${locked} of ${slots.length} locked`;
}

/**
 * The footer: progress, Save draft, and the resend button, which stays off
 * until every flagged item is fixed. The reason is text on the page (and the
 * button's description), not only a gray button.
 */
export function FixFooter({
    formId,
    processing,
    collect,
    title,
    description,
}: {
    formId: string;
    processing: boolean;
    /** The values typed into the flagged items so far, by section then field, for Save draft. */
    collect: () => FixDraftValues;
    title: string;
    description: ReactNode;
}) {
    const { fix, fixedCount, total, allFixed } = useFix();
    const [saving, setSaving] = useState(false);
    const [savedAt, setSavedAt] = useState<string | null>(null);
    const reasonId = 'fix-resend-reason';
    const percent = total === 0 ? 100 : Math.round((fixedCount / total) * 100);

    async function saveDraft() {
        setSaving(true);

        try {
            const response = await fetch(fix.draftUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ sections: collect() }),
            });

            if (!response.ok) {
                throw new Error('save failed');
            }

            setSavedAt(new Date().toISOString());
            notify.success({
                title: 'Draft saved',
                message:
                    'Your changes are saved. A file you chose stays in this window until you resend.',
            });
        } catch {
            notify.error({
                title: 'Couldn’t save the draft',
                message: 'Check your connection and try again.',
            });
        } finally {
            setSaving(false);
        }
    }

    return (
        <FormFooter
            status={
                <span className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <span
                        role="progressbar"
                        aria-label="Items fixed"
                        aria-valuemin={0}
                        aria-valuemax={total}
                        aria-valuenow={fixedCount}
                        aria-valuetext={`${fixedCount} of ${total} fixed`}
                        className="h-1.5 w-28 overflow-hidden rounded-full bg-border"
                    >
                        <span
                            className={cn(
                                'block h-full rounded-full transition-[width]',
                                allFixed ? 'bg-success' : 'bg-warning',
                            )}
                            style={{ width: `${percent}%` }}
                        />
                    </span>
                    <span id={reasonId} className="text-sm text-foreground">
                        {progressText(fixedCount, total)}
                    </span>
                    {savedAt && <span className="sr-only">Draft saved.</span>}
                </span>
            }
        >
            <Button
                type="button"
                variant="outline"
                onClick={saveDraft}
                disabled={saving || processing}
            >
                {saving && <Spinner />}
                Save draft
            </Button>
            <FormSubmitConfirm
                formId={formId}
                processing={processing}
                title={title}
                description={description}
                confirmLabel={fix.resendLabel}
                triggerDisabled={!allFixed}
                triggerDescribedBy={reasonId}
            >
                {fix.resendLabel}
                <Send aria-hidden />
            </FormSubmitConfirm>
        </FormFooter>
    );
}

/**
 * The page shell every Fix and Resend page uses: the title, and the subtitle
 * that names who flagged what. The subtitle depends on the return (the role of
 * the approver who sent it back), so it is built here rather than in each page,
 * which keep their own header text static.
 */
export function FixShell({
    fix,
    children,
}: {
    fix: FixProps;
    children: ReactNode;
}) {
    return (
        <FormShell
            title="Fix and Resend"
            subtitle={
                fix.active
                    ? `Only the items ${fix.role} flagged can be changed. Everything else stays as you sent it.`
                    : `Update the details below and resend to ${fix.role}.`
            }
        >
            {children}
        </FormShell>
    );
}
