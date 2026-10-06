import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Register from '@/pages/auth/register';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a>,
    Form: ({
        children,
    }: {
        children: (state: {
            processing: boolean;
            errors: Record<string, string>;
        }) => ReactNode;
    }) => (
        <form data-testid="register-form">
            {children({ processing: false, errors: {} })}
        </form>
    ),
}));

// Lives outside resources/js/pages/ on purpose: the @inertiajs/vite resolver globs ./pages/**/*.tsx with no test exclusion, so a test file there would be bundled as a page chunk.

const selectedValue = () =>
    new FormData(screen.getByTestId('register-form') as HTMLFormElement).get('intended_path');

describe('Register page — "What do you want to do?" choice', () => {
    it('is a labelled radio group of two options, defaulting to registering a new organization', () => {
        render(<Register passwordRules="minlength: 8;" />);

        const group = screen.getByRole('radiogroup', {
            name: 'What do you want to do?',
        });
        expect(group).toBeInTheDocument();

        const radios = screen.getAllByRole('radio');
        expect(radios).toHaveLength(2);
        expect(
            screen.getByRole('radio', { name: 'Register a new organization' }),
        ).toBeChecked();
        expect(
            screen.getByRole('radio', { name: 'Join an organization' }),
        ).not.toBeChecked();
        expect(selectedValue()).toBe('register_new');
    });

    it('describes each option with its muted line', () => {
        render(<Register passwordRules="" />);

        expect(
            screen.getByRole('radio', { name: 'Register a new organization' }),
        ).toHaveAccessibleDescription('Start one that does not exist yet');
        expect(
            screen.getByRole('radio', { name: 'Join an organization' }),
        ).toHaveAccessibleDescription('Ask to be added as an officer');
    });

    it('selects an option on click and submits its value', async () => {
        const user = userEvent.setup();
        render(<Register passwordRules="" />);

        await user.click(screen.getByText('Join an organization'));

        expect(
            screen.getByRole('radio', { name: 'Join an organization' }),
        ).toBeChecked();
        expect(selectedValue()).toBe('join_existing');
    });

    it('is one tab stop and switches with the arrow keys', async () => {
        const user = userEvent.setup();
        render(<Register passwordRules="" />);

        const register = screen.getByRole('radio', {
            name: 'Register a new organization',
        });
        const join = screen.getByRole('radio', { name: 'Join an organization' });

        // The first-name field autofocuses on load, so start from the group.
        register.focus();

        await user.keyboard('{ArrowDown}');
        expect(join).toHaveFocus();
        expect(join).toBeChecked();
        expect(selectedValue()).toBe('join_existing');

        await user.keyboard('{ArrowUp}');
        expect(register).toHaveFocus();
        expect(register).toBeChecked();

        // One tab stop: Tab leaves the group rather than visiting the other radio.
        await user.tab();
        expect(join).not.toHaveFocus();
        expect(screen.getByLabelText('First name')).toHaveFocus();
    });
});

describe('Register page — name and copy changes', () => {
    it('has First name and Last name side by side instead of a single Name field', () => {
        render(<Register passwordRules="" />);

        const first = screen.getByLabelText('First name');
        const last = screen.getByLabelText('Last name');

        expect(first).toHaveAttribute('name', 'first_name');
        expect(last).toHaveAttribute('name', 'last_name');
        expect(first).toBeRequired();
        expect(last).toBeRequired();
        expect(first.closest('.sm\\:grid-cols-2')).toBe(
            last.closest('.sm\\:grid-cols-2'),
        );
        expect(screen.queryByLabelText('Name')).not.toBeInTheDocument();
    });

    it('uses the new hint and placeholder text', () => {
        render(<Register passwordRules="" />);

        expect(
            screen.getByText('We send a verification code to this address.'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Password')).toHaveAttribute(
            'placeholder',
            'At least 8 characters',
        );
        expect(screen.getByLabelText('Confirm password')).toHaveAttribute(
            'placeholder',
            'Type it again',
        );
    });
});
