import { Form, Head } from '@inertiajs/react';
import StepLeadTimeController from '@/actions/App/Http/Controllers/Admin/StepLeadTimeController';
import CenteredContainer from '@/components/centered-container';
import { RequiredNote } from '@/components/form-shell';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Step = {
    id: number;
    position: number;
    role: string;
    leadTimeDays: number;
};

type Template = {
    id: number;
    formType: string;
    name: string;
    hasVariant: boolean;
    steps: Step[];
};

type Props = {
    templates: Template[];
    defaultDays: number;
    maxDays: number;
};

/**
 * SDAO sets how many days each approval step is given. One form saves every
 * step at once; the server answers with a success toast naming how many steps
 * changed. The clock for a document starts when it reaches the step.
 */
export default function StepLeadTimes({ templates, defaultDays, maxDays }: Props) {
    return (
        <>
            <Head title="Review Lead Times" />

            <CenteredContainer maxWidth="3xl" className="space-y-6">
                <PageHeader
                    title="Review Lead Times"
                    subtitle="How many days each approver has to act once a document reaches their step. Approvers are reminded every 24 hours and alerted once it is overdue."
                />

                <PageNotice tone="info" title={`New steps start at ${defaultDays} days.`}>
                    A change applies right away, including to documents already waiting: the due time is always the day
                    the document reached the step plus the lead time shown here.
                </PageNotice>

                <Form {...StepLeadTimeController.update.form()} options={{ preserveScroll: true }} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <RequiredNote />

                            {templates.map((template) => (
                                <Card key={template.id}>
                                    <CardHeader>
                                        <CardTitle className="text-base">{template.name}</CardTitle>
                                        {template.hasVariant && <CardDescription>{template.formType}</CardDescription>}
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="divide-y">
                                            {template.steps.map((step) => {
                                                const inputId = `lead-time-${step.id}`;
                                                const error = errors[`lead_times.${step.id}`];

                                                return (
                                                    <li key={step.id} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3 first:pt-0 last:pb-0">
                                                        <Label htmlFor={inputId} required className="min-w-0 flex-1 basis-48 leading-snug">
                                                            <span className="font-mono text-xs text-muted-foreground tabular-nums">
                                                                Step {step.position}
                                                            </span>{' '}
                                                            {step.role}
                                                        </Label>
                                                        <div className="flex items-center gap-2">
                                                            <Input
                                                                id={inputId}
                                                                name={`lead_times[${step.id}]`}
                                                                type="number"
                                                                inputMode="numeric"
                                                                min={1}
                                                                max={maxDays}
                                                                step={1}
                                                                required
                                                                aria-required
                                                                aria-invalid={error ? true : undefined}
                                                                aria-describedby={error ? `${inputId}-error` : undefined}
                                                                defaultValue={step.leadTimeDays}
                                                                className="w-20 text-right tabular-nums"
                                                            />
                                                            <span className="text-sm text-muted-foreground">days</span>
                                                        </div>
                                                        <InputError id={`${inputId}-error`} message={error} className="basis-full" />
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </CardContent>
                                </Card>
                            ))}

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <>
                                            <Spinner /> Saving…
                                        </>
                                    ) : (
                                        'Save lead times'
                                    )}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </CenteredContainer>
        </>
    );
}
