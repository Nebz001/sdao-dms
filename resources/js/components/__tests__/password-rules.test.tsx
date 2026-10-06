import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import PasswordRuleHint from '@/components/password-rule-hint';
import { describePasswordRules } from '@/lib/password-rules';
import Register from '@/pages/auth/register';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
    Form: ({
        children,
    }: {
        children: (state: { processing: boolean; errors: Record<string, string> }) => React.ReactNode;
    }) => <form>{children({ processing: false, errors: {} })}</form>,
}));

// Lives outside resources/js/pages/ on purpose: the @inertiajs/vite resolver globs ./pages/**/*.tsx with no test exclusion, so a test file there would be bundled as a page chunk.

// What Password::min(8)->mixedCase()->symbols()->toPasswordRulesString() sends in production.
const PRODUCTION_RULES = 'minlength: 8; required: lower; required: upper; required: special;';

describe('describePasswordRules', () => {
    it('states the production rule in plain words', () => {
        expect(describePasswordRules(PRODUCTION_RULES)).toBe(
            'At least 8 characters, with upper and lower case letters and a symbol.',
        );
    });

    it('states the length only when that is the whole rule', () => {
        expect(describePasswordRules('minlength: 8;')).toBe('At least 8 characters.');
    });

    it('follows whatever the backend sends, so the two cannot drift apart', () => {
        expect(
            describePasswordRules('minlength: 12; required: lower; required: upper; required: digit; required: special;'),
        ).toBe('At least 12 characters, with upper and lower case letters, a number and a symbol.');
        expect(describePasswordRules('minlength: 10; required: digit;')).toBe(
            'At least 10 characters, with a number.',
        );
    });
});

describe('PasswordRuleHint on the register page', () => {
    it('renders a muted hint with the full rule, linked to the password field', () => {
        render(<Register passwordRules={PRODUCTION_RULES} />);

        const field = screen.getByLabelText('Password');
        expect(field).toHaveAttribute('placeholder', 'At least 8 characters');
        expect(field).toHaveAccessibleDescription(
            'At least 8 characters, with upper and lower case letters and a symbol.',
        );
    });

    it('is a standalone muted paragraph', () => {
        render(<PasswordRuleHint id="hint" rules="minlength: 8;" />);

        expect(screen.getByText('At least 8 characters.')).toHaveClass('text-muted-foreground');
    });
});
