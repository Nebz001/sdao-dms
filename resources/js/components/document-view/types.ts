import type { FieldChanges } from '@/types/document-transitions';

/** One event in the unified, newest-first history timeline. */
export type HistoryEvent = {
    /** Unique across both kinds ("transition-4", "remark-4"); ids alone collide. */
    key: string;
    /** A remark is a note an approver added; it never changed the document's state. */
    kind: 'transition' | 'remark';
    id: number;
    action: string;
    step_position: number | null;
    comment: string | null;
    /** Section labels the approver flagged, already resolved server-side. */
    flagged: string[];
    section_notes: { label: string; note: string }[];
    field_changes: FieldChanges | null;
    actor: { name: string; role: string | null } | null;
    created_at: string | null;
};

export type FlowState = 'done' | 'current' | 'upcoming' | 'rejected';

export type FlowNode = {
    key: string;
    name: string;
    actors: string[];
    date: string | null;
    state: FlowState;
    /** The current step, and the viewer is one of its approvers. */
    isYou: boolean;
};

/** Everything the shared document page needs that is not form-specific (App\Approval\DocumentViewData). */
export type DocumentViewData = {
    typeLabel: string;
    subject: string;
    submittedAt: string | null;
    chips: { label: string; value: string }[];
    history: HistoryEvent[];
    /** Whether the viewer may add a remark (the server's `remark` ability), where to post it, and the length cap. */
    remark: { canAdd: boolean; url: string; maxLength: number };
    flow: FlowNode[];
    waiting: {
        step: number;
        totalSteps: number;
        stepName: string;
        days: number;
    } | null;
    quorum: { required: number; approvedBy: string[] } | null;
    record: {
        submittedBy: string | null;
        decidedBy: string | null;
        decidedOn: string | null;
        revisions: number;
        timeToDecide: number | null;
    };
};
