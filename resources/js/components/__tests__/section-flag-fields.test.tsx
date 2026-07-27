import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import SectionFlagFields from '@/components/section-flag-fields';

/**
 * Section-comments redesign — pins the "stateless → stateful" rewrite this
 * component underwent: checking a section now reveals its own optional note
 * field, submitted as `section_comments[key]`, while the checkbox itself
 * keeps submitting as `sections[]` exactly as before (native form
 * serialization, no manual submit handling in this component).
 */
describe('SectionFlagFields', () => {
    const sections = [
        { key: 'contact_information', label: 'Contact Information' },
        { key: 'attachments', label: 'Attachments' },
    ];

    it('renders nothing when there are no sections', () => {
        const { container } = render(<SectionFlagFields sections={[]} />);

        expect(container).toBeEmptyDOMElement();
    });

    it('renders one checkbox per section, with no note fields visible until checked', () => {
        render(<SectionFlagFields sections={sections} />);

        expect(screen.getByRole('checkbox', { name: 'Contact Information' })).toBeInTheDocument();
        expect(screen.getByRole('checkbox', { name: 'Attachments' })).toBeInTheDocument();
        expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    });

    it('still submits as sections[] via a native hidden input, for form compatibility', () => {
        // Radix mirrors the checkbox's state onto a hidden native
        // <input type="checkbox"> for native FormData serialization — that
        // hidden input, not the ARIA-role button itself, carries name/value.
        // Radix only renders it when the checkbox has a <form> ancestor
        // (real usage always does, via the enclosing Inertia <Form>), so the
        // wrapper here is required for this to appear at all.
        const { container } = render(
            <form>
                <SectionFlagFields sections={sections} />
            </form>,
        );

        const hiddenInput = container.querySelector('input[name="sections[]"][value="attachments"]');
        expect(hiddenInput).not.toBeNull();
    });

    it('checking a section reveals a note field scoped to that section, submitted under section_comments[key]', async () => {
        const user = userEvent.setup();
        render(<SectionFlagFields sections={sections} />);

        await user.click(screen.getByRole('checkbox', { name: 'Contact Information' }));

        const note = screen.getByRole('textbox', { name: 'Note for Contact Information' });
        expect(note).toBeInTheDocument();
        expect(note).toHaveAttribute('name', 'section_comments[contact_information]');

        // The other, still-unchecked section has no note field of its own.
        expect(screen.queryByRole('textbox', { name: 'Note for Attachments' })).not.toBeInTheDocument();
    });

    it('unchecking a section removes its note field, so it drops out of the next submission', async () => {
        const user = userEvent.setup();
        render(<SectionFlagFields sections={sections} />);

        const checkbox = screen.getByRole('checkbox', { name: 'Contact Information' });
        await user.click(checkbox);
        expect(screen.getByRole('textbox', { name: 'Note for Contact Information' })).toBeInTheDocument();

        await user.click(checkbox);
        expect(screen.queryByRole('textbox', { name: 'Note for Contact Information' })).not.toBeInTheDocument();
    });
});
