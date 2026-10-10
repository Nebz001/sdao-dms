import { describe, expect, it } from 'vitest';
import { buildNavSections } from '@/lib/nav-config';
import { buildTrail } from '@/lib/nav-trail';
import { layoutKindFor } from '@/lib/page-layout';
import { isStudentAccount } from '@/lib/student-account';
import type { Auth } from '@/types';

const user = { id: 1, name: 'Carl Andrew', first_name: 'Carl' };

function auth(roles: string[], extra: Partial<Auth> = {}): Auth {
    return {
        user,
        roles: roles.map((role) => ({
            role,
            school_id: null,
            program_id: null,
            organization_id: role === 'adviser' ? 4 : null,
        })),
        isActiveOfficer: false,
        canProposeOrganization: false,
        organization: null,
        ...extra,
    } as unknown as Auth;
}

const officer = auth(['student'], { isActiveOfficer: true });
const noOrgStudent = auth([], { canProposeOrganization: true });

describe('which shell a page uses', () => {
    it('gives every signed-in student page the top navbar, settings included', () => {
        for (const name of [
            'dashboard',
            'registrations/index',
            'registrations/show',
            'review/join-requests/index',
            'notifications/index',
            'settings/profile',
            'organizations/mine',
            'hubs/show',
        ]) {
            expect(layoutKindFor(name, officer)).toBe('topbar');
            expect(layoutKindFor(name, noOrgStudent)).toBe('topbar');
        }
    });

    it('keeps the sidebar for approvers, advisers and SDAO', () => {
        for (const role of [
            'sdao_member',
            'adviser',
            'program_chair',
            'dean',
            'principal',
            'academic_director',
            'executive_director',
        ]) {
            expect(layoutKindFor('dashboard', auth([role]))).toBe('sidebar');
            expect(layoutKindFor('settings/profile', auth([role]))).toBe(
                'sidebar',
            );
            expect(layoutKindFor('review/join-requests/index', auth([role]))).toBe(
                'sidebar',
            );
        }
    });

    it('leaves the landing, error and auth pages alone', () => {
        expect(layoutKindFor('welcome', officer)).toBe('none');
        expect(layoutKindFor('errors/error', officer)).toBe('none');
        expect(layoutKindFor('auth/login', null)).toBe('auth');
    });

    it('does not treat a signed-out visitor as a student', () => {
        expect(isStudentAccount(null)).toBe(false);
        expect(layoutKindFor('dashboard', null)).toBe('sidebar');
    });
});

describe('breadcrumbs and back links', () => {
    const sections = buildNavSections(officer, null);

    it('is just Home on the home page, with nowhere to go back to', () => {
        const trail = buildTrail(sections, '/dashboard');

        expect(trail.crumbs.map((c) => c.title)).toEqual(['Home']);
        expect(trail.parent).toBeNull();
    });

    it('shows Home > group for a hub page', () => {
        const trail = buildTrail(sections, '/submit');

        expect(trail.crumbs.map((c) => c.title)).toEqual(['Home', 'Submit']);
        expect(trail.parent?.title).toBe('Home');
    });

    it('shows Home > group > item for a page in a group, and links back to the hub', () => {
        const trail = buildTrail(sections, '/registrations?tab=approved');

        expect(trail.crumbs.map((c) => c.title)).toEqual([
            'Home',
            'My Documents',
            'Registrations',
        ]);
        expect(trail.parent?.title).toBe('My Documents');
        expect(trail.parent?.href).toMatchObject({ url: '/my-documents' });
    });

    it('puts the create pages under Submit', () => {
        const trail = buildTrail(sections, '/renewals/create');

        expect(trail.crumbs.map((c) => c.title)).toEqual([
            'Home',
            'Submit',
            'Renewal',
        ]);
    });

    it('adds one last crumb for a page below an item', () => {
        const edit = buildTrail(sections, '/registrations/5/edit', [
            { title: 'Registrations', href: '/registrations' },
            { title: 'Edit' },
        ]);
        const show = buildTrail(sections, '/registrations/5');

        expect(edit.crumbs.map((c) => c.title)).toEqual([
            'Home',
            'My Documents',
            'Registrations',
            'Edit',
        ]);
        expect(edit.parent?.title).toBe('Registrations');
        expect(show.crumbs.at(-1)?.title).toBe('Details');
    });

    it('makes every crumb but the last a link, and the last one plain', () => {
        const { crumbs } = buildTrail(sections, '/reports');

        expect(crumbs.slice(0, -1).every((c) => c.href !== undefined)).toBe(true);
        expect(crumbs.at(-1)?.href).toBeUndefined();
    });

    it('skips a group that has only one option, like the home card does', () => {
        const trail = buildTrail(sections, '/review/join-requests');

        expect(trail.crumbs.map((c) => c.title)).toEqual([
            'Home',
            'Join Requests',
        ]);
        expect(trail.parent?.title).toBe('Home');
    });

    it('uses the page title for a page the nav config does not list', () => {
        const trail = buildTrail(sections, '/notifications', [
            { title: 'Notifications' },
        ]);

        expect(trail.crumbs.map((c) => c.title)).toEqual([
            'Home',
            'Notifications',
        ]);
    });
});
