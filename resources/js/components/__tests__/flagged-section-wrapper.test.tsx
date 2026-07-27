import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import FlaggedSectionWrapper from '@/components/flagged-section-wrapper';

/**
 * Section-comments redesign — pins the new two-tier comment display added to
 * this wrapper: the general comment and an optional section-specific note
 * are both surfaced in context now, not just the badge that existed before.
 */
describe('FlaggedSectionWrapper', () => {
    it('renders children plainly, with no badge, when the section is not flagged', () => {
        render(
            <FlaggedSectionWrapper sectionKey="contact_information" flagged={[]}>
                <p>Field content</p>
            </FlaggedSectionWrapper>,
        );

        expect(screen.getByText('Field content')).toBeInTheDocument();
        expect(screen.queryByText('Flagged for revision')).not.toBeInTheDocument();
    });

    it('shows the badge but no comment text when flagged with no comments given', () => {
        render(
            <FlaggedSectionWrapper sectionKey="contact_information" flagged={['contact_information']}>
                <p>Field content</p>
            </FlaggedSectionWrapper>,
        );

        expect(screen.getByText('Flagged for revision')).toBeInTheDocument();
    });

    it('shows the general comment when flagged and a comment is given', () => {
        render(
            <FlaggedSectionWrapper
                sectionKey="contact_information"
                flagged={['contact_information']}
                comment="Fix these two things."
            >
                <p>Field content</p>
            </FlaggedSectionWrapper>,
        );

        expect(screen.getByText('Fix these two things.')).toBeInTheDocument();
    });

    it('shows both the general comment and the section-specific note when both are given', () => {
        render(
            <FlaggedSectionWrapper
                sectionKey="contact_information"
                flagged={['contact_information']}
                comment="Fix these two things."
                sectionComment="Phone number is missing."
            >
                <p>Field content</p>
            </FlaggedSectionWrapper>,
        );

        expect(screen.getByText('Fix these two things.')).toBeInTheDocument();
        expect(screen.getByText('Phone number is missing.')).toBeInTheDocument();
    });

    it('shows no comment text at all for a different, unflagged section, even if comments are supplied', () => {
        render(
            <FlaggedSectionWrapper
                sectionKey="contact_information"
                flagged={['attachments']}
                comment="Fix these two things."
                sectionComment="Phone number is missing."
            >
                <p>Field content</p>
            </FlaggedSectionWrapper>,
        );

        expect(screen.queryByText('Fix these two things.')).not.toBeInTheDocument();
        expect(screen.queryByText('Phone number is missing.')).not.toBeInTheDocument();
    });
});
