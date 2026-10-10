import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AttachmentRequirements, { requirementsSummary } from '@/components/attachment-requirements';
import CreateRegistration from '@/pages/registrations/create';
import EditRegistration from '@/pages/registrations/edit';
import CreateRenewal from '@/pages/renewals/create';
import EditRenewal from '@/pages/renewals/edit';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: unknown }) => (
        <a href={typeof href === 'string' ? href : (href as { url: string }).url}>{children}</a>
    ),
    router: { post: vi.fn() },
    Form: ({
        children,
        ...rest
    }: {
        children: (state: { processing: boolean; errors: Record<string, string> }) => ReactNode;
        [key: string]: unknown;
    }) => <form id={rest.id as string}>{children({ processing: false, errors: {} })}</form>,
    usePage: () => ({
        props: {
            auth: { user: { id: 1, name: 'Btan Gomez', first_name: 'Btan', last_name: 'Gomez' } },
            currentPeriod: { academic_year: '2026-2027', term: 'third_term', term_label: '3rd Term', label: '3rd Term, 2026-2027', is_renewal_season: true },
        },
    }),
}));

const slots = [
    { key: 'letter_of_intent', label: 'Letter of Intent', required: true, multiple: false, accept: '.pdf', max_kb: 10240 },
    { key: 'application_form', label: 'Application Form', required: true, multiple: false, accept: '.pdf', max_kb: 10240 },
    { key: 'by_laws', label: 'By-Laws', required: true, multiple: false, accept: '.pdf', max_kb: 10240 },
];

const types = [
    { value: 'co_curricular', label: 'Co-Curricular' },
    { value: 'extra_curricular', label: 'Extra Curricular-Interest Clubs' },
];

function pdf(name: string) {
    return new File(['x'], name, { type: 'application/pdf' });
}

describe('requirements list', () => {
    afterEach(cleanup);

    it('summarizes real counts and the real size limit', () => {
        expect(requirementsSummary(slots, {})).toBe('0 of 3 uploaded · PDF up to 10 MB each');
        expect(requirementsSummary(slots, { letter_of_intent: pdf('a.pdf') })).toBe('1 of 3 uploaded · PDF up to 10 MB each');
        expect(
            requirementsSummary(slots, {}, { by_laws: [{ id: 1, original_filename: 'b.pdf', download_url: '', preview_url: '' }] }),
        ).toBe('1 of 3 uploaded · PDF up to 10 MB each');
    });

    it('numbers each row, turns an uploaded row green with its file name, and offers Replace', async () => {
        const user = userEvent.setup();
        const files: Record<string, File | null> = {};
        const { rerender } = render(
            <AttachmentRequirements slots={slots} files={files} onFileChange={(k, f) => (files[k] = f)} />,
        );

        expect(screen.getAllByRole('button', { name: /^Upload / })).toHaveLength(3);
        expect(screen.getByText('2', { selector: 'span' })).toBeInTheDocument();

        await user.upload(document.getElementById('attachment-letter_of_intent') as HTMLInputElement, pdf('Letter_2027.pdf'));
        rerender(<AttachmentRequirements slots={slots} files={{ ...files }} onFileChange={(k, f) => (files[k] = f)} />);

        expect(screen.getByText('Letter_2027.pdf')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Replace Letter of Intent' })).toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: /^Upload / })).toHaveLength(2);
    });

    it('refuses a file over the limit or of the wrong type, on its own row', async () => {
        const user = userEvent.setup({ applyAccept: false });
        const onFileChange = vi.fn();
        render(<AttachmentRequirements slots={slots} files={{}} onFileChange={onFileChange} />);

        const big = pdf('huge.pdf');
        Object.defineProperty(big, 'size', { value: 11 * 1024 * 1024 });
        await user.upload(document.getElementById('attachment-by_laws') as HTMLInputElement, big);
        expect(screen.getByText(/"huge.pdf" is too large/)).toBeInTheDocument();

        await user.upload(
            document.getElementById('attachment-application_form') as HTMLInputElement,
            new File(['x'], 'photo.gif', { type: 'image/gif' }),
        );
        expect(screen.getByText(/"photo.gif" is not an accepted file type/)).toBeInTheDocument();
        expect(onFileChange).not.toHaveBeenCalledWith('by_laws', expect.any(File));
    });
});

describe('Register a New Organization', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    const props = {
        canPropose: true,
        schools: [{ id: 1, name: 'SACE', type: 'regular', programs: [{ id: 1, name: 'BSCS' }] }],
        organizationTypes: types,
        attachmentSlots: slots,
    };

    it('shows the strip, the sections and the live requirement count', async () => {
        const user = userEvent.setup();
        render(<CreateRegistration {...props} />);

        expect(screen.getByText(/New organization for/)).toHaveTextContent('New organization for 2026-2027');
        expect(screen.getByText('Filed by Btan')).toBeInTheDocument();

        for (const heading of ['About the organization', 'Adviser', 'Contact', 'Requirements']) {
            expect(screen.getByRole('heading', { name: heading })).toBeInTheDocument();
        }

        expect(screen.getByText('0 of 3 uploaded · PDF up to 10 MB each')).toBeInTheDocument();

        await user.upload(document.getElementById('attachment-by_laws') as HTMLInputElement, pdf('Bylaws.pdf'));
        expect(screen.getByText('1 of 3 uploaded · PDF up to 10 MB each')).toBeInTheDocument();
        expect(screen.getByText('Bylaws.pdf')).toBeInTheDocument();
    });

    it('keeps Review and Submit closed until everything is filled, and says what is left', () => {
        render(<CreateRegistration {...props} />);

        expect(screen.getByRole('button', { name: /Review and Submit/ })).toBeDisabled();
        expect(screen.getByText(/Still needed before you can review:.*an adviser.*every requirement/)).toBeInTheDocument();
    });

    it('does not ask for a college until a co-curricular type is chosen', () => {
        render(<CreateRegistration {...props} />);

        expect(screen.queryByText('College')).not.toBeInTheDocument();
        expect(screen.getByRole('combobox', { name: 'Type of organization' })).toBeInTheDocument();
    });

    it('turns a picked adviser into a row with a Change link', async () => {
        const user = userEvent.setup();
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                json: () => Promise.resolve({ advisers: [{ id: 9, name: 'SHS Adviser', email: 'shsadviser@nu-lipa.edu.ph', is_available: true }] }),
            }),
        );
        render(<CreateRegistration {...props} />);

        await user.type(screen.getByPlaceholderText('Search by name or email'), 'shs');
        await user.click(await screen.findByRole('button', { name: /SHS Adviser/ }, { timeout: 3000 }));

        expect(screen.getByText('shsadviser@nu-lipa.edu.ph')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: /Change adviser/ }));
        expect(screen.getByPlaceholderText('Search by name or email')).toBeInTheDocument();
    });
});

describe('Renew Your Organization', () => {
    afterEach(cleanup);

    const base = {
        membership: {
            id: 1,
            position: 'president',
            position_label: 'President',
            organization: { id: 1, name: 'CA Org', college: 'SHS', program: null },
        },
        priorRecord: {
            organization_type: 'co_curricular',
            purpose_of_organization: 'Grow skills.',
            contact_person: 'Carl Andrew',
            contact_no: '09171234567',
            email_address: 'caorg@email.com',
            date_organized: '2024-08-15',
        },
        currentPeriod: { academic_year: '2026-2027', term: 'third_term', label: '3rd Term, 2026-2027' },
        organizationTypes: types,
        attachmentSlots: slots,
    };

    it('opens the form pre-filled, with the name locked and the strip for the coming year', () => {
        render(<CreateRenewal {...base} eligibility={{ status: 'eligible', message: null }} />);

        expect(screen.getByText(/Renewing/)).toHaveTextContent('Renewing CA Org for 2027-2028');
        expect(screen.getByText('Active for 2026-2027')).toBeInTheDocument();
        expect(screen.getByText(/We filled in last year/)).toBeInTheDocument();
        expect(screen.getByText('Can’t be changed in a renewal')).toBeInTheDocument();
        expect(screen.getByLabelText('Contact person')).toHaveValue('Carl Andrew');
        expect(screen.getByLabelText('Purpose')).toHaveValue('Grow skills.');
        expect(screen.getByText('0 of 3 uploaded · PDF up to 10 MB each')).toBeInTheDocument();
        expect(screen.queryByRole('heading', { name: 'Adviser' })).not.toBeInTheDocument();
    });

    it('shows a clear message, and no form, when renewal is not open', () => {
        render(
            <CreateRenewal
                {...base}
                eligibility={{ status: 'season_closed', message: 'Renewal only opens in the 3rd term.' }}
            />,
        );

        expect(screen.getByText('Renewal not available yet.')).toBeInTheDocument();
        expect(screen.getByText('Renewal only opens in the 3rd term.')).toBeInTheDocument();
        expect(screen.queryByLabelText('Contact person')).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Review and Submit/ })).not.toBeInTheDocument();
    });

    it('asks to confirm with a short summary before sending', async () => {
        const user = userEvent.setup();
        render(<CreateRenewal {...base} eligibility={{ status: 'eligible', message: null }} />);

        await user.upload(document.getElementById('attachment-by_laws') as HTMLInputElement, pdf('Bylaws.pdf'));
        await user.click(screen.getByRole('button', { name: /Review and Submit/ }));

        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByText('CA Org')).toBeInTheDocument();
        expect(within(dialog).getByText('2027-2028')).toBeInTheDocument();
        expect(within(dialog).getByText('1 of 3 uploaded')).toBeInTheDocument();
    });
});

describe('resubmitting a returned registration or renewal', () => {
    afterEach(cleanup);

    const attachments = {
        by_laws: [{ id: 4, original_filename: 'Bylaws_v1.pdf', download_url: '/d', preview_url: '/p', size: 1200 }],
    };
    const flags = {
        flaggedSections: ['contact_information', 'by_laws', 'adviser_selection'],
        flaggedComment: 'Please fix these.',
        flaggedSectionComments: {
            contact_information: 'The email bounced.',
            by_laws: 'Wrong version.',
            adviser_selection: 'Pick someone else.',
        },
    };
    const org = { name: 'CA Org', college: 'SHS', program: null };

    it('highlights the flagged sections and the flagged requirement row on a registration', async () => {
        render(
            <EditRegistration
                document={{ id: 3, title: 'Reg', organization: org }}
                detail={{
                    organization_type_label: 'Co-Curricular',
                    purpose_of_organization: 'p',
                    contact_person: 'c',
                    contact_no: '1',
                    email_address: 'e@x.com',
                    date_organized: '2024-01-01',
                    adviser: { id: 2, name: 'Old Adviser' },
                }}
                attachmentSlots={slots}
                attachments={attachments}
                {...flags}
            />,
        );

        expect(screen.getByText('The email bounced.')).toBeInTheDocument();
        expect(screen.getByText('Pick someone else.')).toBeInTheDocument();

        // The comment sits on the By-Laws row, not on its neighbours.
        const byLaws = screen.getByText('By-Laws').closest('li')!;
        expect(within(byLaws).getByText('Wrong version.')).toBeInTheDocument();
        expect(within(byLaws).getByText('Bylaws_v1.pdf')).toBeInTheDocument();
        expect(within(byLaws).getByRole('button', { name: 'Replace By-Laws' })).toBeInTheDocument();
        const letter = screen.getByText('Letter of Intent').closest('li')!;
        expect(within(letter).queryByText('Wrong version.')).not.toBeInTheDocument();

        // The adviser on file shows as the picked row until it is changed.
        expect(screen.getByText('Old Adviser')).toBeInTheDocument();
        expect(screen.getByText('1 of 3 uploaded · PDF up to 10 MB each')).toBeInTheDocument();
    });

    it('highlights the flagged sections and the flagged requirement row on a renewal', () => {
        render(
            <EditRenewal
                document={{ id: 3, title: 'Ren', organization: org }}
                detail={{
                    organization_type_label: 'Co-Curricular',
                    purpose_of_organization: 'p',
                    contact_person: 'c',
                    contact_no: '1',
                    email_address: 'e@x.com',
                    date_organized: '2024-01-01',
                }}
                attachmentSlots={slots}
                attachments={attachments}
                {...flags}
            />,
        );

        expect(screen.getByText('The email bounced.')).toBeInTheDocument();
        const byLaws = screen.getByText('By-Laws').closest('li')!;
        expect(within(byLaws).getByText('Wrong version.')).toBeInTheDocument();
        expect(screen.getAllByText('Flagged for revision').length).toBeGreaterThanOrEqual(2);
    });

});
