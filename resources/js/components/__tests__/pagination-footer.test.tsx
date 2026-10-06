import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import PaginationFooter from '@/components/pagination-footer';

const meta = { from: 1, to: 20, total: 24 };

describe('PaginationFooter', () => {
    it('reads "Showing X to Y of Z" with the word "to", never a dash', () => {
        render(<PaginationFooter meta={meta} links={{ prev: null, next: '/p?page=2' }} onNavigate={() => {}} />);

        expect(screen.getByText('Showing 1 to 20 of 24')).toBeTruthy();
    });

    it('disables Previous on the first page and enables Next', () => {
        render(<PaginationFooter meta={meta} links={{ prev: null, next: '/p?page=2' }} onNavigate={() => {}} />);

        expect((screen.getByRole('button', { name: 'Previous page' }) as HTMLButtonElement).disabled).toBe(true);
        expect((screen.getByRole('button', { name: 'Next page' }) as HTMLButtonElement).disabled).toBe(false);
    });

    it('disables Next on the last page and enables Previous', () => {
        render(
            <PaginationFooter
                meta={{ from: 21, to: 24, total: 24 }}
                links={{ prev: '/p?page=1', next: null }}
                onNavigate={() => {}}
            />,
        );

        expect((screen.getByRole('button', { name: 'Previous page' }) as HTMLButtonElement).disabled).toBe(false);
        expect((screen.getByRole('button', { name: 'Next page' }) as HTMLButtonElement).disabled).toBe(true);
    });

    it('navigates to the matching link when a button is clicked', () => {
        const onNavigate = vi.fn();
        render(<PaginationFooter meta={meta} links={{ prev: '/p?page=1', next: '/p?page=3' }} onNavigate={onNavigate} />);

        fireEvent.click(screen.getByRole('button', { name: 'Next page' }));
        fireEvent.click(screen.getByRole('button', { name: 'Previous page' }));

        expect(onNavigate).toHaveBeenNthCalledWith(1, '/p?page=3');
        expect(onNavigate).toHaveBeenNthCalledWith(2, '/p?page=1');
    });

    it('does not navigate from a disabled button', () => {
        const onNavigate = vi.fn();
        render(<PaginationFooter meta={meta} links={{ prev: null, next: null }} onNavigate={onNavigate} />);

        fireEvent.click(screen.getByRole('button', { name: 'Previous page' }));

        expect(onNavigate).not.toHaveBeenCalled();
    });

    it('is a labelled navigation landmark', () => {
        render(<PaginationFooter meta={meta} links={{ prev: null, next: null }} onNavigate={() => {}} />);

        expect(screen.getByRole('navigation', { name: 'Pagination' })).toBeTruthy();
    });

    it('renders nothing when there are no results', () => {
        const { container } = render(
            <PaginationFooter meta={{ from: null, to: null, total: 0 }} links={{ prev: null, next: null }} onNavigate={() => {}} />,
        );

        expect(container.firstChild).toBeNull();
    });
});
