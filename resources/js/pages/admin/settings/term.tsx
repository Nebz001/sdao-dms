import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import CurrentTermController from '@/actions/App/Http/Controllers/Admin/CurrentTermController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type TermOption = { value: string; label: string };

type Props = {
    current: string;
    terms: TermOption[];
};

export default function CurrentTermSettings({ current, terms }: Props) {
    const [pendingTerm, setPendingTerm] = useState(current);

    const currentLabel = terms.find((t) => t.value === current)?.label ?? current;
    const pendingLabel = terms.find((t) => t.value === pendingTerm)?.label ?? pendingTerm;
    const hasChange = pendingTerm !== current;

    return (
        <>
            <Head title="Current Term" />

            <div className="mx-auto max-w-2xl space-y-6 p-8">
                <div>
                    <h1 className="text-xl font-semibold">Current Term</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        The single, system-wide term new Activity Calendar submissions are
                        automatically filed under. Changing it never alters calendars already
                        submitted — each keeps the term it was submitted under, permanently.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">System-Wide Term</CardTitle>
                        <CardDescription>Currently set to {currentLabel}.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2">
                            <Select value={pendingTerm} onValueChange={setPendingTerm}>
                                <SelectTrigger className="w-full max-w-xs">
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

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button type="button" disabled={!hasChange}>
                                    Update Current Term
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Change current term to {pendingLabel}?</DialogTitle>
                                <DialogDescription>
                                    This applies system-wide: every new Activity Calendar submission
                                    from now on will use {pendingLabel}. Calendars already submitted
                                    under {currentLabel} (or any earlier term) are not affected.
                                </DialogDescription>

                                <Form {...CurrentTermController.update.form()} options={{ preserveScroll: true }}>
                                    {({ processing }) => (
                                        <>
                                            <input type="hidden" name="term" value={pendingTerm} />
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

CurrentTermSettings.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Settings' }, { title: 'Current Term' }],
};
