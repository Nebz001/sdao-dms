/** Mirrors the `kind` field written by each app/Notifications/*::toArray(). */
export type NotificationKind = 'approver_hand_off' | 'document_outcome' | 'account_verified' | 'account_rejected';

export type NotificationItem = {
    id: string;
    kind: NotificationKind | null;
    title: string;
    body: string;
    url: string | null;
    readAt: string | null;
    createdAt: string;
};

export type NotificationsProp = {
    unreadCount: number;
    items: NotificationItem[];
} | null;
