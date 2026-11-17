import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { CountedTextarea } from '@/components/counted-textarea';

function Controlled({ max }: { max: number }) {
    const [value, setValue] = useState('');

    return <CountedTextarea id="t" aria-label="Notes" maxLength={max} value={value} onChange={(e) => setValue(e.target.value)} />;
}

describe('CountedTextarea', () => {
    it('shows the live count against the cap, and sets the browser cap too', async () => {
        const user = userEvent.setup();
        render(<CountedTextarea id="t" aria-label="Notes" maxLength={2000} defaultValue="abc" />);

        const box = screen.getByRole('textbox', { name: 'Notes' });
        expect(box).toHaveAttribute('maxlength', '2000');
        expect(screen.getByText('3 / 2,000')).toBeInTheDocument();

        await user.type(box, 'de');
        expect(screen.getByText('5 / 2,000')).toBeInTheDocument();
    });

    it('describes the textarea by its count, keeping any description it was given', () => {
        render(<CountedTextarea id="t" aria-label="Notes" maxLength={10} aria-describedby="hint" />);

        expect(screen.getByRole('textbox', { name: 'Notes' })).toHaveAttribute('aria-describedby', 'hint t-count');
    });

    it('stays calm below 90%, warns from 90%, and says so at the cap', async () => {
        const user = userEvent.setup();
        render(<Controlled max={20} />);

        const box = screen.getByRole('textbox', { name: 'Notes' });
        const count = () => document.getElementById('t-count')!;

        await user.type(box, 'a'.repeat(17));
        expect(count()).toHaveAttribute('data-tone', 'ok');
        expect(count()).toHaveClass('text-muted-foreground');
        expect(screen.getByRole('status')).toHaveTextContent('');

        await user.type(box, 'aa');
        expect(count()).toHaveAttribute('data-tone', 'near');
        expect(count()).toHaveClass('text-warning-foreground');
        expect(count()).toHaveTextContent('19 / 20 · 1 left');
        expect(screen.getByRole('status')).toHaveTextContent('Approaching the limit.');

        await user.type(box, 'a');
        expect(count()).toHaveAttribute('data-tone', 'full');
        expect(count()).toHaveClass('text-destructive');
        expect(count()).toHaveTextContent('20 / 20 · 0 left');
        expect(screen.getByRole('status')).toHaveTextContent('Limit reached');
    });

    it('stops input at the cap, typed or pasted, controlled or not', async () => {
        const user = userEvent.setup();
        const { unmount } = render(<Controlled max={10} />);

        const box = screen.getByRole('textbox', { name: 'Notes' });
        await user.type(box, 'a'.repeat(15));
        expect(box).toHaveValue('a'.repeat(10));
        await user.click(box);
        await user.paste('zzzzzz');
        expect(box).toHaveValue('a'.repeat(10));
        unmount();

        render(<CountedTextarea id="u" aria-label="Free" maxLength={5} />);
        const free = screen.getByRole('textbox', { name: 'Free' });
        await user.click(free);
        await user.paste('abcdefghij');
        expect(free).toHaveValue('abcde');
        expect(screen.getByText(/5 \/ 5/)).toBeInTheDocument();
    });

    it('calls the caller\'s onChange with the trimmed value', async () => {
        const user = userEvent.setup();
        const seen: string[] = [];
        render(<CountedTextarea id="t" aria-label="Notes" maxLength={3} onChange={(e) => seen.push(e.target.value)} />);

        await user.type(screen.getByRole('textbox', { name: 'Notes' }), 'abcd');

        expect(seen.at(-1)).toBe('abc');
    });
});
