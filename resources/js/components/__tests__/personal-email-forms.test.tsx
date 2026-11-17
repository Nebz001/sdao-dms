import { cleanup, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Register from '@/pages/auth/register';
import Profile from '@/pages/settings/profile';
import VerifyEmailChange from '@/pages/settings/verify-email-change';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a>,
    usePage: () => ({
        props: {
            auth: { user: { id: 1, name: 'Sam Tan', first_name: 'Sam', last_name: 'Tan', email: 'sam.tan@students.nu-lipa.edu.ph', email_verified_at: '2026-01-01' } },
        },
    }),
    Form: ({
        children,
        action,
        ...rest
    }: {
        children: (state: { processing: boolean; errors: Record<string, string> }) => ReactNode;
        action?: string | { url: string };
        [key: string]: unknown;
    }) => (
        <form data-action={typeof action === 'string' ? action : action?.url} data-method={String(rest.method ?? '')}>
            {children({ processing: false, errors: {} })}
        </form>
    ),
}));

vi.mock('@/components/delete-user', () => ({ default: () => null }));

// Lives outside resources/js/pages/ on purpose (see register-page.test.tsx).

afterEach(cleanup);

describe('Register: school email and personal email', () => {
    it('has a required school email that signs in and gets the code, and a required personal email', () => {
        render(<Register passwordRules="" />);

        const school = screen.getByLabelText(/^School email/);
        const personal = screen.getByLabelText(/^Personal email/);

        expect(school).toHaveAttribute('name', 'email');
        expect(school).toBeRequired();
        expect(personal).toHaveAttribute('name', 'personal_email');
        expect(personal).toHaveAttribute('type', 'email');
        expect(personal).toBeRequired();
        expect(personal).toHaveAttribute('maxlength', '255');
        expect(personal).toHaveAttribute('placeholder', 'juan.delacruz@gmail.com');
        expect(school).toHaveAttribute('placeholder', 'juan.delacruz@students.nu-lipa.edu.ph');
        expect(screen.getByText(/verification code here, and only here/)).toBeInTheDocument();
        expect(screen.getByText(/Every notification about your documents is sent here/)).toBeInTheDocument();
    });

    it('places the personal email directly under the school email', () => {
        render(<Register passwordRules="" />);

        const school = screen.getByLabelText(/^School email/);
        const personal = screen.getByLabelText(/^Personal email/);

        expect(school.compareDocumentPosition(personal) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });
});

describe('Settings: the personal email form', () => {
    it('shows a student the personal email form with the current value, as its own form', () => {
        render(<Profile mustVerifyEmail={false} hasPersonalEmail personalEmail="old.personal@gmail.com" />);

        const personal = screen.getByLabelText(/^Personal email/);
        expect(personal).toHaveValue('old.personal@gmail.com');
        expect(personal).toHaveAttribute('placeholder', 'juan.delacruz@gmail.com');
        expect(personal).toHaveAttribute('name', 'personal_email');
        // A PATCH form: Wayfinder posts it with a method override.
        expect(personal.closest('form')?.getAttribute('data-action')).toBe('/settings/profile/personal-email?_method=PATCH');
        expect(screen.getByRole('button', { name: 'Send verification code' })).toBeInTheDocument();
        // The school email is a different form.
        expect(screen.getByLabelText(/^School email/).closest('form')).not.toBe(personal.closest('form'));
        expect(screen.getByText(/a typo cannot cut you off from your notifications/)).toBeInTheDocument();
    });

    it('tells a student with no personal email yet that mail goes to the school address', () => {
        render(<Profile mustVerifyEmail={false} hasPersonalEmail personalEmail={null} />);

        expect(screen.getByText('You have no personal email yet.')).toBeInTheDocument();
        expect(screen.getByText(/notifications are\s+sent to your school email/)).toBeInTheDocument();
        expect(screen.getByLabelText(/^Personal email/)).toHaveValue('');
    });

    it('does not show the personal email form to an account that is not a student', () => {
        render(<Profile mustVerifyEmail={false} hasPersonalEmail={false} personalEmail={null} />);

        expect(screen.queryByLabelText(/^Personal email/)).not.toBeInTheDocument();
        expect(screen.queryByText('You have no personal email yet.')).not.toBeInTheDocument();
        expect(screen.getByLabelText(/^School email/)).toBeInTheDocument();
    });

    it('says the school email is what you sign in with', () => {
        render(<Profile mustVerifyEmail={false} hasPersonalEmail personalEmail="x@gmail.com" />);

        expect(screen.getByText(/This is\s+what you sign in with/)).toBeInTheDocument();
    });
});

describe('Verify screen: school or personal', () => {
    it('words the personal email change as notifications, and posts to the personal routes', () => {
        render(<VerifyEmailChange kind="personal" email="sam.new@gmail.com" resendCooldownSeconds={60} />);

        expect(screen.getByText('Verify your personal email')).toBeInTheDocument();
        expect(screen.getByText('sam.new@gmail.com')).toBeInTheDocument();
        expect(screen.getByText(/Notifications keep going to your current address/)).toBeInTheDocument();
        const actions = Array.from(document.querySelectorAll('form')).map((f) => f.getAttribute('data-action'));
        expect(actions).toContain('/settings/profile/verify-personal-email');
        expect(actions).toContain('/settings/profile/verify-personal-email/resend');
    });

    it('keeps the school email wording and routes by default', () => {
        render(<VerifyEmailChange email="sam.new@students.nu-lipa.edu.ph" resendCooldownSeconds={60} />);

        expect(screen.getByText('Verify your new school email')).toBeInTheDocument();
        expect(screen.getByText(/Your account keeps its current school email/)).toBeInTheDocument();
        const actions = Array.from(document.querySelectorAll('form')).map((f) => f.getAttribute('data-action'));
        expect(actions).toContain('/settings/profile/verify-email');
        expect(actions).not.toContain('/settings/profile/verify-personal-email');
    });
});
