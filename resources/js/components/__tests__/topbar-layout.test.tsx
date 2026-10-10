import { cleanup, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AppTopbarLayout from '@/layouts/app/app-topbar-layout';

const pageMock = vi.hoisted(() => ({
    props: {} as Record<string, unknown>,
    url: '/dashboard',
}));

vi.mock('@inertiajs/react', () => ({
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
    router: { reload: vi.fn(), post: vi.fn() },
    usePage: () => ({ props: pageMock.props, url: pageMock.url }),
}));

vi.mock('@/components/notification-bell', () => ({
    NotificationBell: () => <button type="button">Notifications</button>,
}));
vi.mock('@/components/user-menu-content', () => ({ UserMenuContent: () => null }));
vi.mock('@/components/idle-timeout-dialog', () => ({ default: () => null }));
vi.mock('@/components/ui/toaster', () => ({ Toaster: () => null }));

function renderAt(url: string) {
    pageMock.url = url;
    pageMock.props = {
        auth: {
            user: { id: 1, name: 'Carl Andrew', first_name: 'Carl' },
            roles: [{ role: 'student', school_id: null, program_id: null, organization_id: null }],
            isActiveOfficer: true,
            canProposeOrganization: false,
        },
        navCounts: null,
        currentPeriod: { term_label: '1st Term', academic_year: '2026-2027' },
    };

    render(
        <AppTopbarLayout>
            <p>page body</p>
        </AppTopbarLayout>,
    );
}

afterEach(cleanup);

describe('student top navbar layout', () => {
    it('has no sidebar, but keeps the bell, term and year chips and the user menu', () => {
        renderAt('/dashboard');

        expect(document.querySelector('[data-slot="sidebar"]')).toBeNull();
        expect(document.querySelector('[data-sidebar]')).toBeNull();
        expect(screen.getByRole('button', { name: 'Notifications' })).toBeInTheDocument();
        expect(screen.getByText('1st Term')).toBeInTheDocument();
        expect(screen.getByText('2026-2027')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Account menu for Carl Andrew/ }),
        ).toBeInTheDocument();
        expect(screen.getByText('page body')).toBeInTheDocument();
    });

    it('shows no back link on Home', () => {
        renderAt('/dashboard');

        expect(screen.queryByText(/^Back to/)).not.toBeInTheDocument();
    });

    it('shows Home > My Documents > Registrations with the last item bold and not a link', () => {
        renderAt('/registrations');

        const trail = screen.getByRole('navigation', { name: 'Breadcrumb' });

        expect(within(trail).getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/dashboard');
        expect(within(trail).getByRole('link', { name: 'My Documents' })).toHaveAttribute(
            'href',
            '/my-documents',
        );
        expect(within(trail).queryByRole('link', { name: 'Registrations' })).toBeNull();
        expect(within(trail).getByText('Registrations')).toHaveAttribute('aria-current', 'page');
        expect(within(trail).getByText('Registrations')).toHaveClass('font-semibold');
    });

    it('puts a "Back to {parent}" link under the navbar', () => {
        renderAt('/registrations');

        expect(screen.getByRole('link', { name: 'Back to My Documents' })).toHaveAttribute(
            'href',
            '/my-documents',
        );

        cleanup();
        renderAt('/submit');

        expect(screen.getByRole('link', { name: 'Back to Home' })).toHaveAttribute(
            'href',
            '/dashboard',
        );
    });

    it('collapses the trail below md and leaves the back link', () => {
        renderAt('/registrations');

        const trail = screen.getByRole('navigation', { name: 'Breadcrumb' });

        expect(trail).toHaveClass('hidden', 'md:block');
        expect(screen.getByRole('link', { name: /Back to/ })).not.toHaveClass('hidden');
    });
});
