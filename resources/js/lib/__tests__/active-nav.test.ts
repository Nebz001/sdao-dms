import { describe, expect, it } from 'vitest';
import { resolveActiveNavItem } from '@/lib/active-nav';
import type { NavItem } from '@/types';

const items: NavItem[] = [
    { title: 'Dashboard', href: '/dashboard', alsoActiveOn: ['/admin/dashboard'] },
    { title: 'Submit Registration', href: '/registrations/create' },
    { title: 'My Registrations', href: '/registrations' },
    { title: 'Review Registrations', href: '/review/registrations' },
    { title: 'Provision Approvers', href: '/admin/approvers' },
    { title: 'Venue Calendar', href: '/calendar' },
    { title: 'My Calendars', href: '/activity-calendars' },
];

const titleFor = (path: string) => resolveActiveNavItem(items, path)?.title ?? null;

describe('resolveActiveNavItem', () => {
    it('selects Dashboard on both /dashboard and /admin/dashboard', () => {
        expect(titleFor('/dashboard')).toBe('Dashboard');
        expect(titleFor('/admin/dashboard')).toBe('Dashboard');
    });

    it('keeps the more specific sibling when two hrefs share a prefix', () => {
        expect(titleFor('/registrations/create')).toBe('Submit Registration');
        expect(titleFor('/registrations')).toBe('My Registrations');
    });

    it('keeps detail and edit pages under their section', () => {
        expect(titleFor('/registrations/5')).toBe('My Registrations');
        expect(titleFor('/registrations/5/edit')).toBe('My Registrations');
        expect(titleFor('/review/registrations/5')).toBe('Review Registrations');
        expect(titleFor('/admin/approvers/create')).toBe('Provision Approvers');
    });

    it('does not match on a bare string prefix', () => {
        expect(titleFor('/calendar-archive')).toBeNull();
        expect(titleFor('/activity-calendars/3')).toBe('My Calendars');
    });

    it('ignores query strings and trailing slashes, and returns null off-menu', () => {
        expect(titleFor('/registrations/?page=2')).toBe('My Registrations');
        expect(titleFor('/notifications')).toBeNull();
    });
});
