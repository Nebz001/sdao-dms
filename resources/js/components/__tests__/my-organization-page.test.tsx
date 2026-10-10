import { cleanup, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import MyOrganization from '@/pages/organizations/mine';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        children,
        href,
        ...rest
    }: {
        children: ReactNode;
        href: unknown;
        [key: string]: unknown;
    }) => (
        <a
            href={typeof href === 'string' ? href : (href as { url: string }).url}
            {...rest}
        >
            {children}
        </a>
    ),
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Carl Andrew' },
                organization: { id: 1, name: 'CA Org', logoUrl: null, school: null },
            },
        },
    }),
}));

const base = {
    organization: { id: 1, name: 'CA Org', school: 'Senior High School', program: null },
    status: 'active',
    renewalDue: false,
    coversThroughAcademicYear: '2026-2027',
    requirements: [
        { key: 'registration_approved', label: 'Registration approved', met: true },
        { key: 'adviser_bound', label: 'Adviser bound', met: true },
        { key: 'president_bound', label: 'Active president', met: true },
        { key: 'secretary_bound', label: 'Active secretary', met: false },
        { key: 'renewal_filed', label: 'Renewal filed for next year', met: false },
    ],
    officers: [
        {
            id: 1,
            user: { id: 1, name: 'Carl Andrew Mangobos', email: 'carl@students.nu-lipa.edu.ph' },
            position: 'president',
            position_label: 'President',
        },
    ],
    adviser: { id: 9, name: 'SHS Adviser', email: 'shs@nu-lipa.edu.ph' },
};

afterEach(cleanup);

describe('My Organization page', () => {
    it('shows the header chips: school, coverage and the people count', () => {
        render(<MyOrganization {...base} />);

        expect(screen.getByText('Senior High School')).toBeInTheDocument();
        expect(screen.getByText('2026-2027')).toBeInTheDocument();
        expect(screen.getByText('1 officer, 1 adviser')).toBeInTheDocument();
    });

    it('hides the school chip for an organization with no college', () => {
        render(<MyOrganization {...base} organization={{ ...base.organization, school: null }} />);

        expect(screen.queryByText('Senior High School')).not.toBeInTheDocument();
    });

    it('says 3 of 4 done with an open row for the missing secretary, and no renewal row out of season', () => {
        render(<MyOrganization {...base} />);

        expect(screen.getByText('3 of 4')).toBeInTheDocument();
        expect(screen.getByRole('progressbar', { name: 'Requirements done' })).toHaveAttribute(
            'aria-valuenow',
            '3',
        );
        expect(screen.getByText('Registration approved')).toBeInTheDocument();
        expect(screen.getByText('Add a secretary')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Request officer change' }),
        ).toHaveAttribute('href', '/organizations/officer-change');
        expect(screen.queryByText('File a renewal for next year')).not.toBeInTheDocument();
    });

    it('adds the renewal row while renewal applies', () => {
        render(<MyOrganization {...base} renewalDue />);

        expect(screen.getByText('3 of 5')).toBeInTheDocument();
        expect(screen.getByText('File a renewal for next year')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Start renewal' })).toHaveAttribute(
            'href',
            '/renewals/create',
        );
    });

    it('marks the current user with You and shows an empty secretary seat with an Add link', () => {
        render(<MyOrganization {...base} />);

        const officers = screen.getByText('Officers').closest('[data-slot="card"]') as HTMLElement;

        expect(within(officers).getByText('You')).toBeInTheDocument();
        expect(within(officers).getByText('carl@students.nu-lipa.edu.ph')).toBeInTheDocument();
        expect(within(officers).getByText('No secretary yet')).toBeInTheDocument();
        expect(within(officers).getByRole('link', { name: 'Add a secretary' })).toHaveAttribute(
            'href',
            '/organizations/officer-change',
        );
    });

    it('shows the adviser, or an honest empty row', () => {
        render(<MyOrganization {...base} />);

        expect(screen.getByText('shs@nu-lipa.edu.ph')).toBeInTheDocument();

        cleanup();
        render(<MyOrganization {...base} adviser={null} />);

        expect(screen.getByText('No adviser yet')).toBeInTheDocument();
    });
});
