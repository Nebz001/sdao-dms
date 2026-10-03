import { StatusBadge } from '@/components/status-badge';
import type { RecentDecision } from './types';

/** Approved (green), Returned (amber) or Rejected (red), in normal case. */
export default function ResultPill({ result }: { result: RecentDecision['result'] }) {
    return <StatusBadge status={result} className="text-xs tracking-normal normal-case" />;
}
