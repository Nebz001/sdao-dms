import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import ProposalDetails from '@/components/document-view/proposal-details';
import type { ProposalData } from '@/components/document-view/proposal-details';
import {
    CriteriaMechanicsSection,
    ObjectivesSection,
    ResponsiblePersonsSection,
} from '@/components/document-view/proposal-text';

// Lives outside resources/js/pages/ on purpose (see register-page.test.tsx).

describe('ObjectivesSection', () => {
    const text =
        'To strengthen the preparedness of students.\n\nSpecific Objectives:\n\nProvide a realistic exam.\n\n\n\nIdentify strengths.\n\nEncourage discipline.';

    it('shows the main objective as a paragraph and the specific ones as a numbered list', () => {
        render(<ObjectivesSection text={text} />);

        expect(screen.getByRole('heading', { name: 'Objectives' })).toBeInTheDocument();
        expect(screen.getByText('To strengthen the preparedness of students.')).toBeInTheDocument();

        const sub = screen.getByRole('heading', { name: 'Specific objectives' });
        expect(sub).toHaveClass('uppercase', 'text-muted-foreground');

        const list = screen.getByRole('list', { name: 'Specific objectives' });
        const items = within(list).getAllByRole('listitem');
        expect(items.map((li) => li.textContent)).toEqual([
            '1Provide a realistic exam.',
            '2Identify strengths.',
            '3Encourage discipline.',
        ]);
    });

    it('collapses blank lines and shows plain paragraphs when there is no Specific Objectives line', () => {
        const { container } = render(<ObjectivesSection text={'First.\n\n\n\n\nSecond.'} />);

        expect(screen.queryByRole('list')).not.toBeInTheDocument();
        expect(container.querySelectorAll('p')).toHaveLength(2);
    });

    it('never drops the label when nothing follows it', () => {
        render(<ObjectivesSection text={'Main\n\nSpecific Objectives:'} />);

        expect(screen.getByText('Specific Objectives:')).toBeInTheDocument();
        expect(screen.queryByRole('list')).not.toBeInTheDocument();
    });
});

describe('CriteriaMechanicsSection', () => {
    const text = 'Participants: 4th year Accountancy students\nExam Coverage: Auditing, Taxation\nBring a valid ID.\nDuration: 8 hours, with breaks';

    it('is labelled "Criteria and mechanics" with no quote bar', () => {
        const { container } = render(<CriteriaMechanicsSection text={text} />);

        expect(screen.getByRole('heading', { name: 'Criteria and mechanics' })).toBeInTheDocument();
        expect(container.querySelector('.border-l-2')).toBeNull();
        expect(container.querySelector('blockquote')).toBeNull();
    });

    it('reads Label: value lines as a description list, exactly as written', () => {
        const { container } = render(<CriteriaMechanicsSection text={text} />);

        const terms = Array.from(container.querySelectorAll('dt')).map((e) => e.textContent);
        const details = Array.from(container.querySelectorAll('dd')).map((e) => e.textContent);

        expect(terms).toEqual(['Participants', 'Exam Coverage', 'Duration']);
        expect(details).toEqual(['4th year Accountancy students', 'Auditing, Taxation', '8 hours, with breaks']);
        expect(container.querySelectorAll('dl')).toHaveLength(2);
    });

    it('keeps a line without a label as a paragraph between the pairs, in order', () => {
        const { container } = render(<CriteriaMechanicsSection text={text} />);

        expect(screen.getByText('Bring a valid ID.').tagName).toBe('P');
        const order = Array.from(container.querySelectorAll('dl, p')).map((e) => e.tagName);
        expect(order).toEqual(['DL', 'P', 'DL']);
    });

    it('stacks label over value on narrow screens and uses two columns from sm up', () => {
        const { container } = render(<CriteriaMechanicsSection text="Duration: 8 hours" />);

        const row = container.querySelector('dt')!.parentElement!;
        expect(row).toHaveClass('flex-col', 'sm:grid');
    });

    it('is plain paragraphs when no line has a label', () => {
        const { container } = render(<CriteriaMechanicsSection text={'Judged on creativity.\nAnd teamwork.'} />);

        expect(container.querySelector('dl')).toBeNull();
        expect(container.querySelectorAll('p')).toHaveLength(2);
    });
});

describe('ResponsiblePersonsSection', () => {
    it('shows one row per stored entry: bold name, muted role, initials without the title', () => {
        render(
            <ResponsiblePersonsSection
                entries={['Juan Dela Cruz — JPIA President', 'Maria Santos, Activity Chairperson', 'Prof. Reyes — Faculty Adviser', 'Ana Lim']}
            />,
        );

        const rows = screen.getAllByRole('listitem');
        expect(rows).toHaveLength(4);

        expect(within(rows[0]).getByText('Juan Dela Cruz')).toHaveClass('font-medium');
        expect(within(rows[0]).getByText('JPIA President')).toHaveClass('text-muted-foreground');
        expect(within(rows[0]).getByText('JC')).toBeInTheDocument();
        expect(within(rows[1]).getByText('Activity Chairperson')).toBeInTheDocument();
        // The title stays in the shown name but not in the initials.
        expect(within(rows[2]).getByText('Prof. Reyes')).toBeInTheDocument();
        expect(within(rows[2]).getByText('R')).toBeInTheDocument();
        // No role: only the name.
        expect(rows[3].textContent).toBe('ALAna Lim');
    });

    it('does not invent a Lead tag', () => {
        render(<ResponsiblePersonsSection entries={['Juan — President', 'Maria — Chair']} />);

        expect(screen.queryByText(/lead/i)).not.toBeInTheDocument();
    });

    it('renders nothing for an empty list', () => {
        const { container } = render(<ResponsiblePersonsSection entries={[]} />);

        expect(container).toBeEmptyDOMElement();
    });
});

describe('ProposalDetails uses the shared sections', () => {
    const proposal: ProposalData = {
        calendar_mode: 'on_calendar',
        title: 'Mock CPA Board Exam',
        objectives: 'Main objective.\n\nSpecific Objectives:\nOne\nTwo',
        activity_description: 'Describes\n\n\n\nthe activity.',
        criteria_mechanics: 'Participants: Everyone',
        program_flow: '9:00 Opening\n\n\n10:00 Exam',
        expenses: null,
        expense_items: null,
        expense_items_total: null,
        responsible_persons: ['Juan Dela Cruz — President'],
        proposed_budget: null,
        activity_nature_label: null,
        activity_type_label: null,
        partner_organizations: null,
        target_sdg_labels: [],
        budget_source_label: null,
    };

    it('shows every narrative section, none of the person\'s text lost', () => {
        const { container } = render(<ProposalDetails organizationName="JPIA" proposal={proposal} activity={null} />);

        for (const heading of ['Objectives', 'Specific objectives', 'Activity description', 'Criteria and mechanics', 'Program flow', 'Responsible persons']) {
            expect(screen.getByRole('heading', { name: heading })).toBeInTheDocument();
        }

        expect(container.textContent).toContain('9:00 Opening');
        expect(container.textContent).toContain('10:00 Exam');
        expect(container.querySelector('.border-l-2')).toBeNull();
    });
});
