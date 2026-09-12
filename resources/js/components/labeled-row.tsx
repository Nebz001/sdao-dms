/**
 * Label/value row for a read-only summary card — shared by
 * activity-proposals/show.tsx, review/activity-proposals/show.tsx, and
 * step-two.tsx's step-1 carryover display (Group D item 5), which all
 * rendered this identically as a locally-defined component.
 */
export function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-3 gap-2">
            <span className="font-medium text-muted-foreground">{label}</span>
            <span className="col-span-2">{value}</span>
        </div>
    );
}
