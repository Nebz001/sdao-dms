import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { DetailField } from '@/components/document-view/details';
import FlowCard from '@/components/document-view/flow-card';
import { formatDateTime, formatDuration, formatFileSize, formatLongDate, formatPhone } from '@/components/document-view/format';
import HistoryCard from '@/components/document-view/history-card';
import ReviewPanel from '@/components/document-view/review-panel';
import type { DocumentViewData, FlowNode, HistoryEvent } from '@/components/document-view/types';

const baseView: DocumentViewData = {
    typeLabel: 'Registration',
    subject: 'CODECS',
    submittedAt: '2026-09-12T00:00:00Z',
    chips: [],
    history: [],
    remark: { canAdd: false, url: '/documents/1/remarks', maxLength: 1000 },
    flow: [],
    waiting: { step: 3, totalSteps: 4, stepName: 'SDAO review', days: 3 },
    quorum: null,
    record: { submittedBy: 'Miguel Torres', decidedBy: null, decidedOn: null, revisions: 0, timeToDecide: null },
};

describe('formatting', () => {
    it('formats dates, phone numbers, sizes and durations for display', () => {
        expect(formatLongDate('2024-08-07')).toBe('August 7, 2024');
        expect(formatLongDate(null)).toBeNull();
        expect(formatPhone('09175236732')).toBe('0917 523 6732');
        expect(formatPhone('+63 2 8123 4567')).toBe('+63 2 8123 4567');
        expect(formatFileSize(245760)).toBe('240 KB');
        expect(formatFileSize(1153434)).toBe('1.1 MB');
        expect(formatFileSize(null)).toBeNull();
        expect(formatDuration(1)).toBe('1 day');
        expect(formatDuration(0)).toBe('Under a day');
    });
});

describe('DetailField', () => {
    it('shows "Not provided" for an empty value, and nothing when hideIfEmpty', () => {
        const { rerender } = render(
            <dl>
                <DetailField label="Adviser">{null}</DetailField>
            </dl>,
        );
        expect(screen.getByText('Not provided')).toBeInTheDocument();

        rerender(
            <dl>
                <DetailField label="Adviser" hideIfEmpty>
                    {null}
                </DetailField>
            </dl>,
        );
        expect(screen.queryByText('Adviser')).not.toBeInTheDocument();
    });
});

describe('ReviewPanel', () => {
    const props = { view: baseView, noun: 'registration', audience: 'approver' as const };

    it('shows the "Waiting on you" banner and the decision card while the document waits on the viewer', () => {
        render(<ReviewPanel {...props} status="in_review" canAct decision={<div>decision card</div>} />);

        expect(screen.getByText('Waiting on you')).toBeInTheDocument();
        expect(screen.getByText(/Step 3 of 4, SDAO review/)).toBeInTheDocument();
        expect(screen.getByText(/Waiting 3 days/)).toBeInTheDocument();
        expect(screen.getByText('decision card')).toBeInTheDocument();
    });

    it('never mounts the decision card when the document is not waiting on the viewer', () => {
        render(<ReviewPanel {...props} status="in_review" canAct={false} decision={<div>decision card</div>} />);

        expect(screen.queryByText('decision card')).not.toBeInTheDocument();
        expect(screen.getByText(/No action is needed from you/)).toBeInTheDocument();
    });

    it('shows the matching status card for each decided state', () => {
        const { rerender } = render(<ReviewPanel {...props} status="approved" canAct={false} />);
        expect(screen.getByText('Approved')).toBeInTheDocument();
        expect(screen.getByText(/fully approved/)).toBeInTheDocument();

        rerender(
            <ReviewPanel
                {...props}
                view={{ ...baseView, record: { ...baseView.record, decidedOn: '2026-09-12T00:00:00Z' } }}
                status="rejected"
                canAct={false}
            />,
        );
        expect(screen.getByText('Rejected')).toBeInTheDocument();
        expect(screen.getByText(/must file a new one/)).toBeInTheDocument();

        rerender(<ReviewPanel {...props} status="returned" canAct={false} />);
        expect(screen.getByText('Returned for revision')).toBeInTheDocument();
    });

    it('renders page notices in every state', () => {
        render(<ReviewPanel {...props} status="approved" canAct={false} notices={<p>venue conflict</p>} />);

        expect(screen.getByText('venue conflict')).toBeInTheDocument();
    });
});

describe('FlowCard', () => {
    const flow: FlowNode[] = [
        { key: 'submitted', name: 'Submitted', actors: ['Miguel Torres'], date: '2026-09-11T12:00:00Z', state: 'done', isYou: false },
        { key: 'step-1', name: 'SDAO Member review', actors: ['Zaira Joy Enayo'], date: null, state: 'current', isYou: true },
        { key: 'completed', name: 'Completed', actors: [], date: null, state: 'upcoming', isYou: false },
    ];

    it('marks completed, current and upcoming steps and flags the viewer’s own step', () => {
        render(<FlowCard flow={flow} />);

        expect(screen.getByText('You are here')).toBeInTheDocument();
        expect(screen.getByText('Current step')).toBeInTheDocument();
        expect(screen.getByText('September 11, 2026')).toBeInTheDocument();
    });

    it('says when a step has no one assigned yet', () => {
        render(<FlowCard flow={[{ ...flow[1], actors: [], isYou: false }]} />);

        expect(screen.getByText('No one assigned yet')).toBeInTheDocument();
    });
});

describe('HistoryCard', () => {
    const base = { step_position: 1, comment: null, flagged: [], section_notes: [], field_changes: null };
    const events: HistoryEvent[] = [
        {
            ...base,
            key: 'transition-3',
            kind: 'transition' as const,
            id: 3,
            action: 'resubmitted',
            actor: { name: 'Miguel Torres', role: 'President' },
            created_at: '2026-09-12T00:01:00Z',
            field_changes: {
                organization_details: {
                    label: 'Organization Details',
                    status: 'changed',
                    fields: [{ key: 'adviser', label: 'Adviser', old: null, new: 'Adviser One', changed: true }],
                },
            },
        },
        {
            ...base,
            key: 'transition-2',
            kind: 'transition' as const,
            id: 2,
            action: 'returned',
            comment: 'Please correct the type of organization.',
            flagged: ['Organization details'],
            actor: { name: 'Carl Justin Magpantay', role: 'Adviser' },
            created_at: '2026-09-11T16:18:00Z',
        },
        {
            ...base,
            key: 'transition-1',
            kind: 'transition' as const,
            id: 1,
            action: 'submitted',
            actor: { name: 'Miguel Torres', role: 'President' },
            created_at: '2026-09-11T08:00:00Z',
        },
    ];

    it('labels a remark, shows its author and role, and uses the same time format as every other event', () => {
        render(
            <HistoryCard
                events={[
                    {
                        ...base,
                        key: 'remark-9',
                        kind: 'remark',
                        id: 9,
                        action: 'remark',
                        step_position: null,
                        comment: 'Please bring the signed copy.',
                        actor: { name: 'Carl Magpantay', role: 'Adviser' },
                        created_at: '2026-09-11T16:30:00Z',
                    },
                ]}
            />,
        );

        expect(screen.getByText('Remark')).toBeInTheDocument();
        expect(screen.getByText('Carl Magpantay, Adviser')).toBeInTheDocument();
        expect(screen.getByText('Please bring the signed copy.')).toBeInTheDocument();
        expect(screen.getByText(formatDateTime('2026-09-11T16:30:00Z') as string)).toBeInTheDocument();
    });

    it('lists every event with its count, actor and role', () => {
        render(<HistoryCard events={events} />);

        expect(screen.getByText('3 events')).toBeInTheDocument();
        expect(screen.getAllByText('Miguel Torres, President')).toHaveLength(2);
        expect(screen.getByText('Carl Justin Magpantay, Adviser')).toBeInTheDocument();
    });

    it('shows a return as a quoted message with flagged section badges', () => {
        render(<HistoryCard events={events} />);

        expect(screen.getByText('Please correct the type of organization.').tagName).toBe('BLOCKQUOTE');
        expect(screen.getByText('Flagged: Organization details')).toBeInTheDocument();
    });

    it('shows a resubmission’s changes with "Not set" when there was no prior value', () => {
        render(<HistoryCard events={events} />);

        const diff = screen.getByText('Changes made on this revision').parentElement as HTMLElement;
        expect(within(diff).getByText('Not set')).toHaveClass('line-through');
        expect(within(diff).getByText('Adviser One')).toBeInTheDocument();
    });

    it('explains an empty history', () => {
        render(<HistoryCard events={[]} />);

        expect(screen.getByText('0 events')).toBeInTheDocument();
        expect(screen.getByText(/Nothing has happened yet/)).toBeInTheDocument();
    });
});
