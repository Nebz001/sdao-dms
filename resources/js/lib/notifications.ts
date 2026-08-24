import { Ban, Bell, CircleCheck, CircleX, FileText, Inbox, Undo2, UserCheck, UserPlus } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { NotificationItem } from '@/types/notifications';

export function xsrfToken(): string {
    return decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

export type NotificationVisual = { Icon: LucideIcon; iconClassName: string; chipClassName: string };

/**
 * Reuses the icon+color language already established elsewhere in the app —
 * CircleCheck/CircleX/Undo2 + success/destructive/warning are exactly
 * ApprovalActionsCard's Approve/Return/Reject icons, and UserCheck/UserPlus/
 * Ban are the sidebar's and dashboard's account- and join-request icons
 * (UserCheck: "Pending Accounts"; UserPlus: "Join an Organization" /
 * "Provision Approvers"; Ban: dashboard's "Account not approved" alert) —
 * rather than inventing a new icon vocabulary just for this dropdown.
 * `chipClassName` is the matching tinted circle behind the icon, shown only
 * while a row is unread — see NotificationRow. Shared by the bell dropdown
 * and the full /notifications page so the two can never show a different
 * icon or color for the same kind of notification.
 */
export function notificationVisual(item: NotificationItem): NotificationVisual {
    switch (item.kind) {
        case 'approver_hand_off':
            return { Icon: Inbox, iconClassName: 'text-info', chipClassName: 'bg-info/15' };
        case 'document_outcome':
            switch (item.status) {
                case 'approved':
                    return { Icon: CircleCheck, iconClassName: 'text-success', chipClassName: 'bg-success/15' };
                case 'rejected':
                    return { Icon: CircleX, iconClassName: 'text-destructive', chipClassName: 'bg-destructive/15' };
                case 'returned':
                    return { Icon: Undo2, iconClassName: 'text-warning', chipClassName: 'bg-warning/15' };
                default:
                    return { Icon: FileText, iconClassName: 'text-muted-foreground', chipClassName: 'bg-muted' };
            }
        case 'account_verified':
        case 'approver_provisioned':
        case 'join_request_approved':
            return { Icon: UserCheck, iconClassName: 'text-success', chipClassName: 'bg-success/15' };
        case 'account_rejected':
        case 'join_request_declined':
            return { Icon: Ban, iconClassName: 'text-destructive', chipClassName: 'bg-destructive/15' };
        case 'join_request_received':
            return { Icon: UserPlus, iconClassName: 'text-info', chipClassName: 'bg-info/15' };
        default:
            return { Icon: Bell, iconClassName: 'text-muted-foreground', chipClassName: 'bg-muted' };
    }
}
