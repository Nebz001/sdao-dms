/** Mirrors the `kind` field written by each app/Notifications/*::toArray(). */
export type NotificationKind =
    | 'approver_hand_off'
    | 'document_outcome'
    | 'account_verified'
    | 'account_rejected'
    | 'approver_provisioned'
    | 'join_request_received'
    | 'join_request_approved'
    | 'join_request_declined'
    | 'renewal_window_opened';

export type NotificationItem = {
    id: string;
    kind: NotificationKind | null;
    title: string;
    body: string;
    /** Only meaningfully populated for `document_outcome` ('approved' | 'rejected' | 'returned'). */
    status: string | null;
    url: string | null;
    readAt: string | null;
    createdAt: string;
};

export type NotificationsProp = {
    unreadCount: number;
    items: NotificationItem[];
} | null;

export type NotificationStatusFilter = 'all' | 'unread' | 'read';

/** The paginated `notifications` prop shape for the full /notifications page (NotificationController::index). */
export type NotificationsPage = {
    data: NotificationItem[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    links: { prev: string | null; next: string | null };
};
