import { Card, CardContent } from '@/components/ui/card';
import { CardHeading, DetailField } from './details';
import { formatDuration, formatLongDate } from './format';
import type { DocumentViewData } from './types';

/** Submitted by, decided by, decided on, revisions and time to decide. */
export default function RecordCard({ record }: { record: DocumentViewData['record'] }) {
    const pending = 'Pending';

    return (
        <Card>
            <CardHeading title="Record" />
            <CardContent>
                <dl className="flex flex-col gap-4">
                    <DetailField label="Submitted by">{record.submittedBy}</DetailField>
                    <DetailField label="Decided by">
                        {record.decidedBy ?? <span className="font-normal text-muted-foreground">{pending}</span>}
                    </DetailField>
                    <DetailField label="Decided on">
                        {formatLongDate(record.decidedOn) ?? (
                            <span className="font-normal text-muted-foreground">{pending}</span>
                        )}
                    </DetailField>
                    <DetailField label="Revisions">{String(record.revisions)}</DetailField>
                    <DetailField label="Time to decide">
                        {formatDuration(record.timeToDecide) ?? (
                            <span className="font-normal text-muted-foreground">{pending}</span>
                        )}
                    </DetailField>
                </dl>
            </CardContent>
        </Card>
    );
}
