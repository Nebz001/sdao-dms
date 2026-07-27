import { Form, Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import RegistrationController from '@/actions/App/Http/Controllers/RegistrationController';
import AttachmentSlotField from '@/components/attachment-slot-field';
import type {AttachmentSlotDef, ExistingAttachment} from '@/components/attachment-slot-field';
import FlaggedSectionWrapper from '@/components/flagged-section-wrapper';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import * as registrations from '@/routes/registrations';
import type { FlaggedRevisionProps } from '@/types';

type OrganizationTypeOption = {
    value: string;
    label: string;
};

type DocumentData = {
    id: number;
    title: string;
    organization: { name: string; college: string | null; program: string | null };
};

type AdviserResult = { id: number; name: string; email: string; is_available: boolean };

type DetailData = {
    organization_type: string;
    purpose_of_organization: string;
    contact_person: string;
    contact_no: string;
    email_address: string;
    date_organized: string;
    adviser: { id: number; name: string } | null;
} | null;

type Props = {
    document: DocumentData;
    detail: DetailData;
    organizationTypes: OrganizationTypeOption[];
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
} & FlaggedRevisionProps;

export default function EditRegistration({
    document,
    detail,
    organizationTypes,
    attachmentSlots,
    attachments,
    flaggedSections,
    flaggedComment,
    flaggedSectionComments,
}: Props) {
    // Return-for-revision preserves the ability to pick a NEW adviser (Phase
    // 2 item 5). Left untouched, the existing adviser is kept — this is a
    // separate, small controlled-state island alongside the rest of the
    // uncontrolled Form fields below, submitted via a hidden input.
    const [adviserQuery, setAdviserQuery] = useState(detail?.adviser?.name ?? '');
    const [adviserResults, setAdviserResults] = useState<AdviserResult[]>([]);
    const [selectedAdviserId, setSelectedAdviserId] = useState<number | null>(null);
    // Only shown once the student actually edits the field — the initial
    // background search against the pre-filled current adviser name must
    // stay silent, not flash "no results"/"searching" over a valid value.
    const [adviserTouched, setAdviserTouched] = useState(false);
    const [adviserSearchStatus, setAdviserSearchStatus] = useState<'idle' | 'searching' | 'done'>('idle');
    const [adviserSearchFailed, setAdviserSearchFailed] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latestAdviserQuery = useRef(adviserQuery);

    const searchAdvisers = useCallback((query: string) => {
        if (query.trim() === '') {
            setAdviserResults([]);
            setAdviserSearchStatus('idle');
            setAdviserSearchFailed(false);

            return;
        }

        setAdviserSearchStatus('searching');
        setAdviserSearchFailed(false);

        fetch(registrations.adviserSearch.url({ query: { q: query } }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => res.json())
            .then((data) => {
                // Stale-response guard: ignore a slow reply for a query the
                // student has since changed or cleared.
                if (latestAdviserQuery.current !== query) {
                    return;
                }

                setAdviserResults(data.advisers ?? []);
                setAdviserSearchStatus('done');
            })
            .catch(() => {
                if (latestAdviserQuery.current !== query) {
                    return;
                }

                setAdviserSearchFailed(true);
                setAdviserSearchStatus('done');
            });
    }, []);

    useEffect(() => {
        latestAdviserQuery.current = adviserQuery;

        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        debounceTimer.current = setTimeout(() => searchAdvisers(adviserQuery), 600);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [adviserQuery, searchAdvisers]);

    return (
        <>
            <Head title="Edit Registration" />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title="Edit & Resubmit Registration"
                    description="Update the details below and resubmit for SDAO review."
                />

                {/* Organization Name / College / Program (Phase 2 item 7 slice 2) —
                    read-only field-presence parity; not editable here. */}
                <div className="grid gap-1 rounded-md border p-4 text-sm">
                    <p>
                        <span className="font-medium">Organization Name:</span> {document.organization.name}
                    </p>
                    <p>
                        <span className="font-medium">College:</span> {document.organization.college ?? '—'}
                    </p>
                    {document.organization.program && (
                        <p>
                            <span className="font-medium">Program:</span> {document.organization.program}
                        </p>
                    )}
                </div>

                {flaggedSections.includes('general') && (
                    <div className="rounded-md border border-destructive/60 bg-destructive/10 p-3 text-sm text-destructive">
                        <p className="font-medium">General revisions requested</p>
                        {flaggedSectionComments.general && <p className="mt-1">{flaggedSectionComments.general}</p>}
                        {flaggedComment && <p className="mt-1 text-destructive/80">{flaggedComment}</p>}
                    </div>
                )}

                <Form
                    {...RegistrationController.update.form({ document: document.id })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Adviser (Phase 2 item 5) — untouched keeps the current adviser */}
                            <FlaggedSectionWrapper
                                sectionKey="adviser_selection"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.adviser_selection}
                            >
                            <div className="grid gap-2">
                                <Label htmlFor="adviser">Adviser</Label>
                                <Input
                                    id="adviser"
                                    placeholder="Search to change adviser…"
                                    value={adviserQuery}
                                    onChange={(e) => {
                                        setAdviserQuery(e.target.value);
                                        setSelectedAdviserId(null);
                                        setAdviserTouched(true);
                                    }}
                                    autoComplete="off"
                                />
                                <input type="hidden" name="adviser_id" value={selectedAdviserId ?? ''} />
                                {adviserTouched && adviserQuery.trim() !== '' && adviserSearchStatus === 'searching' && (
                                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Spinner className="size-3.5" /> Searching advisers…
                                    </p>
                                )}
                                {adviserTouched && adviserSearchStatus === 'done' && adviserSearchFailed && (
                                    <p className="text-sm text-destructive">Couldn't search advisers just now. Try again.</p>
                                )}
                                {adviserTouched &&
                                    adviserSearchStatus === 'done' &&
                                    !adviserSearchFailed &&
                                    adviserResults.length === 0 &&
                                    selectedAdviserId === null && (
                                        <p className="text-sm text-muted-foreground">
                                            No matching adviser found. Check the spelling, or contact SDAO if this
                                            adviser should be listed.
                                        </p>
                                    )}
                                {adviserResults.length > 0 && (
                                    <div className="rounded-md border divide-y">
                                        {adviserResults.map((a) => (
                                            <button
                                                key={a.id}
                                                type="button"
                                                onClick={() => {
                                                    setSelectedAdviserId(a.id);
                                                    setAdviserResults([]);
                                                    setAdviserSearchStatus('idle');
                                                    setAdviserSearchFailed(false);
                                                    setAdviserQuery(a.name);
                                                }}
                                                className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-accent"
                                            >
                                                <span>
                                                    {a.name} <span className="text-muted-foreground">({a.email})</span>
                                                </span>
                                                {!a.is_available && (
                                                    <span className="text-xs text-warning-foreground">
                                                        Assigned elsewhere
                                                    </span>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )}
                                <InputError message={errors.adviser_id} />
                            </div>
                            </FlaggedSectionWrapper>

                            <FlaggedSectionWrapper
                                sectionKey="organization_details"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.organization_details}
                            >
                            <div className="space-y-6">
                            {/* Organization type */}
                            <div className="grid gap-2">
                                <Label htmlFor="organization_type">Type of Organization</Label>
                                <Select
                                    name="organization_type"
                                    defaultValue={detail?.organization_type}
                                    required
                                >
                                    <SelectTrigger id="organization_type" className="w-full">
                                        <SelectValue placeholder="Select type…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {organizationTypes.map((t) => (
                                            <SelectItem key={t.value} value={t.value}>
                                                {t.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.organization_type} />
                            </div>

                            {/* Date organized */}
                            <div className="grid gap-2">
                                <Label htmlFor="date_organized">Date Organized</Label>
                                <Input
                                    id="date_organized"
                                    type="date"
                                    name="date_organized"
                                    defaultValue={detail?.date_organized}
                                    required
                                />
                                <InputError message={errors.date_organized} />
                            </div>

                            {/* Purpose of organization */}
                            <div className="grid gap-2">
                                <Label htmlFor="purpose_of_organization">Purpose of Organization</Label>
                                <Textarea
                                    id="purpose_of_organization"
                                    name="purpose_of_organization"
                                    defaultValue={detail?.purpose_of_organization}
                                    rows={4}
                                    required
                                />
                                <InputError message={errors.purpose_of_organization} />
                            </div>
                            </div>
                            </FlaggedSectionWrapper>

                            <FlaggedSectionWrapper
                                sectionKey="contact_information"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.contact_information}
                            >
                            <div className="space-y-6">
                            {/* Contact person */}
                            <div className="grid gap-2">
                                <Label htmlFor="contact_person">Contact Person</Label>
                                <Input
                                    id="contact_person"
                                    name="contact_person"
                                    defaultValue={detail?.contact_person}
                                    required
                                />
                                <InputError message={errors.contact_person} />
                            </div>

                            {/* Contact no. */}
                            <div className="grid gap-2">
                                <Label htmlFor="contact_no">Contact No.</Label>
                                <Input
                                    id="contact_no"
                                    name="contact_no"
                                    defaultValue={detail?.contact_no}
                                    required
                                />
                                <InputError message={errors.contact_no} />
                            </div>

                            {/* Email address */}
                            <div className="grid gap-2">
                                <Label htmlFor="email_address">Email Address</Label>
                                <Input
                                    id="email_address"
                                    type="email"
                                    name="email_address"
                                    defaultValue={detail?.email_address}
                                    required
                                />
                                <InputError message={errors.email_address} />
                            </div>
                            </div>
                            </FlaggedSectionWrapper>

                            <FlaggedSectionWrapper
                                sectionKey="attachments"
                                flagged={flaggedSections}
                                comment={flaggedComment}
                                sectionComment={flaggedSectionComments.attachments}
                            >
                            <div className="space-y-6">
                            {attachmentSlots.map((slot) => (
                                <AttachmentSlotField
                                    key={slot.key}
                                    slot={slot}
                                    existing={attachments[slot.key]}
                                    error={errors[`attachments.${slot.key}`]}
                                />
                            ))}
                            </div>
                            </FlaggedSectionWrapper>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Save & Resubmit</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

EditRegistration.layout = {
    breadcrumbs: [{ title: 'Registrations' }, { title: 'Edit' }],
};
