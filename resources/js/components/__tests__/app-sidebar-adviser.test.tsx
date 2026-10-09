import { cleanup, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AppSidebar } from '@/components/app-sidebar';

type Entry = { title: string; items?: Entry[] };

const pageMock = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/react', () => ({
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
    router: { reload: vi.fn() },
    usePage: () => ({ props: pageMock.props, url: '/dashboard' }),
}));

// The sidebar's chrome is not under test: only which entries it builds.
vi.mock('@/components/ui/sidebar', () => {
    const Pass = ({ children }: { children?: ReactNode }) => <div>{children}</div>;

    return {
        Sidebar: Pass,
        SidebarContent: Pass,
        SidebarFooter: Pass,
        SidebarHeader: Pass,
        SidebarMenu: Pass,
        SidebarMenuButton: Pass,
        SidebarMenuItem: Pass,
    };
});
vi.mock('@/components/nav-user', () => ({ NavUser: () => null }));
vi.mock('@/components/app-logo', () => ({ default: () => null }));
vi.mock('@/components/org-branding', () => ({ default: () => null }));
vi.mock('@/components/nav-main', () => ({
    NavMain: ({ entries }: { entries: Entry[] }) => (
        <ul>
            {entries.flatMap((entry) =>
                (entry.items ?? [entry]).map((item) => (
                    <li key={item.title}>{item.title}</li>
                )),
            )}
        </ul>
    ),
}));

function renderFor(role: { role: string; organization_id: number | null }) {
    pageMock.props = {
        auth: {
            user: { id: 1 },
            roles: [role],
            isActiveOfficer: false,
            canProposeOrganization: true,
        },
        navCounts: { review: {}, documents: {}, accounts: {} },
    };

    render(<AppSidebar />);
}

describe('AppSidebar — adviser entries', () => {
    beforeEach(() => {
        pageMock.props = {};
    });

    it('shows the proposal review, join requests and Manage Officers to an adviser bound to an organization', () => {
        renderFor({ role: 'adviser', organization_id: 4 });

        expect(screen.getByText('Proposals')).toBeInTheDocument();
        expect(screen.getByText('Join Requests')).toBeInTheDocument();
        expect(screen.getByText('Manage Officers')).toBeInTheDocument();
    });

    it('hides the proposal review from an adviser with no organization', () => {
        renderFor({ role: 'adviser', organization_id: null });

        expect(screen.queryByText('Proposals')).not.toBeInTheDocument();
        expect(screen.queryByText('Join Requests')).not.toBeInTheDocument();
        expect(screen.queryByText('Manage Officers')).not.toBeInTheDocument();
    });

    it('never offers an unassigned adviser the found-an-organization entry', () => {
        renderFor({ role: 'adviser', organization_id: null });

        expect(screen.queryByText('Registration')).not.toBeInTheDocument();
    });

    it('shows the report review to an adviser bound to an organization and to SDAO, but not to an unassigned adviser or a dean', () => {
        renderFor({ role: 'adviser', organization_id: 4 });
        expect(screen.getByText('Reports')).toBeInTheDocument();
        cleanup();

        renderFor({ role: 'sdao_member', organization_id: null });
        expect(screen.getByText('Reports')).toBeInTheDocument();
        cleanup();

        renderFor({ role: 'adviser', organization_id: null });
        expect(screen.queryByText('Reports')).not.toBeInTheDocument();
        cleanup();

        renderFor({ role: 'dean', organization_id: null });
        expect(screen.queryByText('Reports')).not.toBeInTheDocument();
    });

    it('still shows the proposal review to the other approver roles', () => {
        renderFor({ role: 'dean', organization_id: null });

        expect(screen.getByText('Proposals')).toBeInTheDocument();
    });
});
