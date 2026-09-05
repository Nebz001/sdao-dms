import { Form, Head } from '@inertiajs/react';
import { useRef, useState } from 'react';
import CurrentPeriodController from '@/actions/App/Http/Controllers/Admin/CurrentPeriodController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type TermOption = { value: string; label: string; order: number };
type AcademicYearOption = { value: string; label: string };

type CurrentPeriod = {
    academic_year: string;
    term: string;
    label: string;
    is_renewal_season: boolean;
};

type Props = {
    current: CurrentPeriod;
    terms: TermOption[];
    academicYears: AcademicYearOption[];
    suggestedAcademicYearOnWrap: string;
    renewalNoticeRecipientCount: number;
};

export default function CurrentPeriodSettings({
    current,
    terms,
    academicYears,
    suggestedAcademicYearOnWrap,
    renewalNoticeRecipientCount,
}: Props) {
    const [pendingTerm, setPendingTerm] = useState(current.term);
    const [pendingYear, setPendingYear] = useState(current.academic_year);
    const yearTouched = useRef(false);

    // Auto-suggests the incremented academic year only when wrapping 3rd
    // term back to 1st, and only while the admin hasn't touched the year
    // field themselves — so the suggestion never fights a deliberate choice,
    // but a wrong year stays correctable at any time. Driven directly by the
    // term Select's change handler (not an effect) so the suggestion applies
    // exactly once, at the moment of the term choice.
    function handleTermChange(term: string) {
        setPendingTerm(term);

        if (yearTouched.current) {
            return;
        }

        if (term === 'first_term' && current.term === 'third_term') {
            setPendingYear(suggestedAcademicYearOnWrap);
        } else if (term === current.term) {
            setPendingYear(current.academic_year);
        }
    }

    const termLabel = (value: string) => terms.find((t) => t.value === value)?.label ?? value;
    const hasChange = pendingTerm !== current.term || pendingYear !== current.academic_year;
    const opensRenewalSeason = pendingTerm === 'third_term' && current.term !== 'third_term';
    const pendingIsEarlier =
        (terms.find((t) => t.value === pendingTerm)?.order ?? 0) < (terms.find((t) => t.value === current.term)?.order ?? 0) &&
        pendingYear === current.academic_year;
    const movesBackwards = pendingYear < current.academic_year || pendingIsEarlier;

    return (
        <>
            <Head title="Current Period" />

            <div className="max-w-2xl space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Current Period
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        The single, system-wide academic term and year new Activity
                        Calendar submissions are filed under. Setting the term to 3rd
                        opens organization renewal season and notifies every
                        organization whose renewal is due. Changing the period never
                        alters documents already submitted — each keeps the period it
                        was submitted or approved under, permanently.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            System-Wide Academic Period
                        </CardTitle>
                        <CardDescription>
                            Currently set to {current.label}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-col gap-4 sm:flex-row">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="pending-term">Term</Label>
                                <Select value={pendingTerm} onValueChange={handleTermChange}>
                                    <SelectTrigger id="pending-term" className="w-full">
                                        <SelectValue placeholder="Select term…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {terms.map((t) => (
                                            <SelectItem key={t.value} value={t.value}>
                                                {t.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="pending-year">Academic Year</Label>
                                <Select
                                    value={pendingYear}
                                    onValueChange={(value) => {
                                        yearTouched.current = true;
                                        setPendingYear(value);
                                    }}
                                >
                                    <SelectTrigger id="pending-year" className="w-full">
                                        <SelectValue placeholder="Select academic year…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {academicYears.map((y) => (
                                            <SelectItem key={y.value} value={y.value}>
                                                {y.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button type="button" disabled={!hasChange}>
                                    Update Current Period
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    Change current period to {termLabel(pendingTerm)}, {pendingYear}?
                                </DialogTitle>
                                <DialogDescription className="space-y-2">
                                    <span className="block">
                                        This applies system-wide: every new Activity Calendar
                                        submission from now on uses this period. Documents already
                                        submitted or approved under {current.label} (or any earlier
                                        period) are not affected.
                                    </span>
                                    {opensRenewalSeason && (
                                        <span className="block font-medium text-foreground">
                                            {renewalNoticeRecipientCount > 0
                                                ? `This opens renewal season — ${renewalNoticeRecipientCount} officer(s) will be emailed that renewal is now due.`
                                                : 'This opens renewal season. No organizations currently appear to be due.'}
                                        </span>
                                    )}
                                    {movesBackwards && (
                                        <span className="block font-medium text-destructive">
                                            This moves the system backwards. Organizations that
                                            already renewed for a later period will appear active
                                            again.
                                        </span>
                                    )}
                                </DialogDescription>

                                <Form
                                    {...CurrentPeriodController.update.form()}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <>
                                            <input type="hidden" name="term" value={pendingTerm} />
                                            <input type="hidden" name="academic_year" value={pendingYear} />
                                            <DialogFooter className="gap-2">
                                                <DialogClose asChild>
                                                    <Button type="button" variant="secondary">
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button type="submit" disabled={processing}>
                                                    {processing ? (
                                                        <>
                                                            <Spinner /> Updating…
                                                        </>
                                                    ) : (
                                                        'Confirm Change'
                                                    )}
                                                </Button>
                                            </DialogFooter>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CurrentPeriodSettings.layout = {
    breadcrumbs: [
        { title: 'Admin' },
        { title: 'Settings' },
        { title: 'Current Period' },
    ],
};
