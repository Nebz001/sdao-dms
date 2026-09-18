import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { UserInfo } from '@/components/user-info';
import type { User } from '@/types';

function user(overrides: Partial<User> = {}): User {
    return {
        id: 1,
        name: 'Torres, Marco',
        email: 'torresm@students.nu-lipa.edu.ph',
        email_verified_at: '2026-01-01T00:00:00Z',
        account_status: 'verified',
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
        ...overrides,
    };
}

/**
 * subtitle is the sidebar footer's new second line (nav-user.tsx passes a
 * president/secretary's org school here); showEmail is the pre-existing
 * dropdown behavior (user-menu-content.tsx). Kept as separate props so
 * neither call site's rendering can affect the other's — see
 * user-info.tsx's docblock.
 */
describe('UserInfo', () => {
    it('renders the subtitle line when passed', () => {
        render(<UserInfo user={user()} subtitle="School of Computing and IT" />);

        expect(screen.getByText('School of Computing and IT')).toBeInTheDocument();
    });

    it('renders no second line at all when subtitle is omitted — not a placeholder string', () => {
        const { container } = render(<UserInfo user={user()} />);

        // Only the name's <span> should exist in the text block; nothing
        // else stands in for a missing subtitle (e.g. no "No college").
        const spans = container.querySelectorAll('div.grid > span');
        expect(spans).toHaveLength(1);
        expect(spans[0]).toHaveTextContent('Torres, Marco');
    });

    it('still renders the email independently when showEmail is true — the dropdown case is unaffected', () => {
        render(<UserInfo user={user()} showEmail />);

        expect(screen.getByText('torresm@students.nu-lipa.edu.ph')).toBeInTheDocument();
    });

    it('renders both the email and the subtitle when both are given, without interfering with each other', () => {
        render(<UserInfo user={user()} showEmail subtitle="School of Computing and IT" />);

        expect(screen.getByText('torresm@students.nu-lipa.edu.ph')).toBeInTheDocument();
        expect(screen.getByText('School of Computing and IT')).toBeInTheDocument();
    });
});
