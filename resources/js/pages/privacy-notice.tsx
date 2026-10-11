import { Form, Head, router } from '@inertiajs/react';
import {
    Check,
    FileText,
    Info,
    Lock,
    Mail,
    MapPin,
    ShieldCheck,
    User,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import PrivacyNoticeController from '@/actions/App/Http/Controllers/PrivacyNoticeController';
import AppLogoLockup from '@/components/app-logo-lockup';
import IdleTimeoutDialog from '@/components/idle-timeout-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Toaster } from '@/components/ui/toaster';
import { cn } from '@/lib/utils';

type Props = {
    version: string;
};

const SECTION_LINKS = [
    { id: 'collect', label: 'What we collect' },
    { id: 'use', label: 'Why we use it' },
    { id: 'see', label: 'Who can see it' },
    { id: 'keep', label: 'How long we keep it' },
    { id: 'protect', label: 'How we protect it' },
    { id: 'rights', label: 'Your rights' },
];

const SUMMARY: {
    icon: LucideIcon;
    tone: string;
    title: string;
    text: string;
}[] = [
    {
        icon: FileText,
        tone: 'bg-primary/10 text-primary-text',
        title: 'We collect what’s needed',
        text: 'Your account details and the documents your organization files.',
    },
    {
        icon: User,
        tone: 'bg-info/15 text-info-foreground',
        title: 'Only the right people see it',
        text: 'Your officers and the approvers in your document’s route.',
    },
    {
        icon: Lock,
        tone: 'bg-success/15 text-success-foreground',
        title: 'We never sell your data',
        text: 'It’s used only for SDAO processes and NU Lipa records.',
    },
    {
        icon: Info,
        tone: 'bg-warning/15 text-warning-foreground',
        title: 'You have rights',
        text: 'You can ask to see, correct, or question how your data is used.',
    },
];

const VIEWERS = [
    {
        title: 'Officers of your organization',
        text: 'See your organization’s documents and their status.',
    },
    {
        title: 'Approvers in the route',
        text: 'Your adviser, college dean, and SDAO staff see the documents sent to them.',
    },
    {
        title: 'SDAO administrators',
        text: 'Manage accounts, organizations, and records across the system.',
    },
    {
        title: 'Others, only if required',
        text: 'Service providers that host the system, and authorities when required by law.',
    },
];

function Bullets({ items }: { items: [string, string][] }) {
    return (
        <ul className="flex list-disc flex-col gap-2 pl-5 text-sm leading-relaxed text-muted-foreground marker:text-muted-foreground">
            {items.map(([label, text]) => (
                <li key={label}>
                    <strong className="font-semibold text-foreground">
                        {label}:
                    </strong>{' '}
                    {text}
                </li>
            ))}
        </ul>
    );
}

function Section({
    id,
    number,
    title,
    children,
}: {
    id: string;
    number: number;
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            aria-labelledby={`${id}-heading`}
            className="flex scroll-mt-6 flex-col gap-3 px-4 py-6 sm:px-6"
        >
            <div className="flex items-center gap-3">
                <span
                    aria-hidden
                    className="flex size-6 shrink-0 items-center justify-center rounded-md bg-primary/10 text-xs font-semibold text-primary-text"
                >
                    {number}
                </span>
                <h2 id={`${id}-heading`} className="text-lg font-semibold">
                    {title}
                </h2>
            </div>
            {children}
        </section>
    );
}

function Paragraph({ children }: { children: ReactNode }) {
    return (
        <p className="text-sm leading-relaxed text-muted-foreground">
            {children}
        </p>
    );
}

function ContactRow({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <div className="flex items-start gap-2 text-sm text-muted-foreground">
            <Icon aria-hidden className="mt-0.5 size-4 shrink-0" />
            <span className="min-w-0 break-words">{children}</span>
        </div>
    );
}

function formatNoticeDate(version: string): string {
    const date = new Date(`${version}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? version
        : date.toLocaleDateString('en-PH', {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
          });
}

export default function PrivacyNotice({ version }: Props) {
    const [agreed, setAgreed] = useState(false);
    const [declining, setDeclining] = useState(false);

    function decline() {
        setDeclining(true);
        router.post(
            PrivacyNoticeController.decline.url(),
            {},
            {
                onSuccess: () => router.flushAll(),
                onFinish: () => setDeclining(false),
            },
        );
    }

    return (
        <>
            <Head title="Privacy Notice" />

            <Form
                {...PrivacyNoticeController.accept.form()}
                className="flex min-h-svh flex-col bg-background"
            >
                {({ processing, errors }) => (
                    <>
                        <header className="border-b">
                            <div className="mx-auto flex w-full max-w-4xl items-center gap-3 px-4 py-3 sm:px-6">
                                <AppLogoLockup className="h-10 shrink-0" />
                                <span className="text-sm font-semibold">
                                    SDAO Document System
                                </span>
                            </div>
                        </header>

                        <main
                            id="main-content"
                            className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 px-4 py-8 sm:px-6"
                        >
                            <div className="flex items-center gap-4">
                                <div className="flex size-12 shrink-0 items-center justify-center rounded-xl border bg-primary/10 text-primary-text">
                                    <ShieldCheck
                                        aria-hidden
                                        className="size-6"
                                    />
                                </div>
                                <div className="min-w-0">
                                    <h1 className="text-2xl font-semibold">
                                        Privacy Notice
                                    </h1>
                                    <p className="text-sm text-muted-foreground">
                                        Please read and accept before using the
                                        SDAO Document System
                                    </p>
                                </div>
                            </div>

                            <Card className="gap-0 overflow-hidden py-0">
                                <div className="flex flex-col gap-1 border-b bg-muted/40 px-4 py-2.5 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                    <span>
                                        National University Lipa ·{' '}
                                        <span className="font-semibold text-foreground">
                                            Student Development and Activities
                                            Office
                                        </span>
                                    </span>
                                    <span>
                                        Under the Data Privacy Act of 2012 (RA
                                        10173)
                                    </span>
                                </div>

                                <div className="flex flex-col gap-5 px-4 py-6 sm:px-6">
                                    <Paragraph>
                                        We value your privacy and are committed
                                        to protecting your personal information.
                                        This notice explains how National
                                        University Lipa (“NU Lipa”) collects,
                                        uses, and protects your data when you
                                        use the{' '}
                                        <strong className="font-semibold text-foreground">
                                            SDAO Document System
                                        </strong>
                                        , the system student organizations,
                                        advisers, deans, and SDAO staff use to
                                        file and approve registrations,
                                        renewals, activity proposals, and
                                        reports.
                                    </Paragraph>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {SUMMARY.map((item) => (
                                            <div
                                                key={item.title}
                                                className="flex items-start gap-3 rounded-lg border p-3"
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-8 shrink-0 items-center justify-center rounded-md',
                                                        item.tone,
                                                    )}
                                                >
                                                    <item.icon
                                                        aria-hidden
                                                        className="size-4"
                                                    />
                                                </span>
                                                <div className="flex min-w-0 flex-col gap-0.5">
                                                    <span className="text-sm font-semibold">
                                                        {item.title}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {item.text}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    <nav
                                        aria-label="Notice sections"
                                        className="flex flex-wrap gap-2"
                                    >
                                        {SECTION_LINKS.map((link) => (
                                            <a
                                                key={link.id}
                                                href={`#${link.id}`}
                                                className="rounded-full border px-3 py-1 text-xs hover:bg-muted focus-visible:focus-ring"
                                            >
                                                {link.label}
                                            </a>
                                        ))}
                                    </nav>
                                </div>

                                <Separator />

                                <Section
                                    id="collect"
                                    number={1}
                                    title="Personal data we collect"
                                >
                                    <Bullets
                                        items={[
                                            [
                                                'Account details',
                                                'your name, NU Lipa email, student or employee number, role (officer, adviser, dean, or SDAO staff), college, and program.',
                                            ],
                                            [
                                                'Organization records',
                                                'organization name, type, officers and their positions, adviser, contact person, contact number, and organization email.',
                                            ],
                                            [
                                                'Documents you file',
                                                'registrations, renewals, activity calendars, activity proposals, after-activity reports, officer change requests, and their attachments such as letters, photos, attendance sheets, and evaluation forms.',
                                            ],
                                            [
                                                'Approval records',
                                                'each approver’s decision, notes, and the date and time a document moved through its route.',
                                            ],
                                            [
                                                'System data',
                                                'login times, actions taken in the system, notifications sent, and basic device and browser details needed to keep the system secure.',
                                            ],
                                        ]}
                                    />
                                </Section>

                                <Separator />

                                <Section
                                    id="use"
                                    number={2}
                                    title="Why we use your data"
                                >
                                    <Paragraph>
                                        NU Lipa processes your data for specific
                                        and legitimate purposes, relying on
                                        applicable legal bases such as consent,
                                        compliance with legal obligations, and
                                        NU Lipa’s legitimate interests in
                                        running student activities.
                                    </Paragraph>
                                    <Bullets
                                        items={[
                                            [
                                                'Organization recognition',
                                                'reviewing registrations, renewals, and officer changes.',
                                            ],
                                            [
                                                'Activity approval',
                                                'routing proposals and reports to the right adviser, dean, and SDAO staff, and checking venue and calendar conflicts.',
                                            ],
                                            [
                                                'Updates',
                                                'notifying you when a document is approved, returned, or needs your action.',
                                            ],
                                            [
                                                'Records and audits',
                                                'keeping an official history of student activities for SDAO and NU Lipa requirements.',
                                            ],
                                        ]}
                                    />
                                </Section>

                                <Separator />

                                <Section
                                    id="see"
                                    number={3}
                                    title="Who can see your data"
                                >
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {VIEWERS.map((viewer) => (
                                            <div
                                                key={viewer.title}
                                                className="flex flex-col gap-1 rounded-lg border p-3"
                                            >
                                                <span className="text-sm font-semibold">
                                                    {viewer.title}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {viewer.text}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                    <div
                                        role="note"
                                        className="flex items-start gap-2 rounded-lg border border-success/40 bg-success/10 px-3 py-2 text-xs text-success-foreground"
                                    >
                                        <Check
                                            aria-hidden
                                            className="mt-0.5 size-4 shrink-0"
                                        />
                                        NU Lipa does not sell your personal
                                        data. It is shared only on a
                                        need-to-know basis, with proper
                                        safeguards.
                                    </div>
                                    <Paragraph>
                                        Some service providers may store data
                                        outside the Philippines. Where this
                                        applies, NU Lipa uses safeguards in line
                                        with the law and NU Lipa policies.
                                    </Paragraph>
                                </Section>

                                <Separator />

                                <Section
                                    id="keep"
                                    number={4}
                                    title="How long we keep it"
                                >
                                    <Paragraph>
                                        NU Lipa keeps your data only as long as
                                        it’s needed for the purposes above and
                                        for legal or records requirements, then
                                        disposes of it securely. Approved
                                        documents and organization records may
                                        be archived longer as part of SDAO’s
                                        official records of student activities.
                                    </Paragraph>
                                </Section>

                                <Separator />

                                <Section
                                    id="protect"
                                    number={5}
                                    title="How we protect it"
                                >
                                    <Bullets
                                        items={[
                                            [
                                                'Role-based access',
                                                'you only see what your role allows.',
                                            ],
                                            [
                                                'Activity logs',
                                                'actions in the system are recorded and monitored.',
                                            ],
                                            [
                                                'Secure connections and storage',
                                                'technical safeguards such as secure settings and, where appropriate, encryption.',
                                            ],
                                            [
                                                'Trained staff',
                                                'people who handle your data follow confidentiality rules.',
                                            ],
                                        ]}
                                    />
                                </Section>

                                <Separator />

                                <Section
                                    id="rights"
                                    number={6}
                                    title="Your rights and how to contact us"
                                >
                                    <Paragraph>
                                        Under the Data Privacy Act, you have the
                                        right to be informed, to access, object
                                        to, correct, and erase or block your
                                        data, and to file a complaint, subject
                                        to law and NU Lipa policies. For
                                        questions or requests, contact:
                                    </Paragraph>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="flex flex-col gap-2 rounded-lg border p-3">
                                            <span className="flex items-center gap-2 text-sm font-semibold">
                                                <Lock
                                                    aria-hidden
                                                    className="size-4 text-primary-text"
                                                />
                                                NU Lipa Data Privacy Office
                                            </span>
                                            <ContactRow icon={MapPin}>
                                                NU Bldg. SM City Lipa, Ayala
                                                Highway, Lipa City, Batangas
                                            </ContactRow>
                                            <ContactRow icon={Mail}>
                                                <strong className="font-semibold text-foreground">
                                                    NU Lipa:
                                                </strong>{' '}
                                                <a
                                                    href="mailto:cop@nu-lipa.edu.ph"
                                                    className="text-primary-text underline"
                                                >
                                                    cop@nu-lipa.edu.ph
                                                </a>
                                            </ContactRow>
                                            <ContactRow icon={Mail}>
                                                <strong className="font-semibold text-foreground">
                                                    NU Data Privacy Officer:
                                                </strong>{' '}
                                                <a
                                                    href="mailto:dpo@national-u.edu.ph"
                                                    className="text-primary-text underline"
                                                >
                                                    dpo@national-u.edu.ph
                                                </a>
                                            </ContactRow>
                                        </div>
                                        <div className="flex flex-col gap-2 rounded-lg border p-3">
                                            <span className="flex items-center gap-2 text-sm font-semibold">
                                                <FileText
                                                    aria-hidden
                                                    className="size-4 text-primary-text"
                                                />
                                                SDAO Office
                                            </span>
                                            <ContactRow icon={MapPin}>
                                                [SDAO OFFICE LOCATION]
                                            </ContactRow>
                                            <ContactRow icon={Mail}>
                                                [SDAO EMAIL]
                                            </ContactRow>
                                            <ContactRow icon={Info}>
                                                For wrong account or
                                                organization details
                                            </ContactRow>
                                        </div>
                                    </div>
                                </Section>

                                <Separator />

                                <p className="px-4 py-4 text-xs text-muted-foreground sm:px-6">
                                    Last updated: {formatNoticeDate(version)}
                                </p>
                            </Card>
                        </main>

                        <div className="sticky bottom-0 z-10 border-t bg-background pb-[env(safe-area-inset-bottom)]">
                            <div className="mx-auto flex w-full max-w-4xl flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div className="flex flex-col gap-1">
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="agree"
                                            name="agree"
                                            checked={agreed}
                                            onCheckedChange={(value) =>
                                                setAgreed(value === true)
                                            }
                                            aria-invalid={
                                                errors.agree ? true : undefined
                                            }
                                        />
                                        <Label
                                            htmlFor="agree"
                                            className="text-sm leading-snug font-normal"
                                        >
                                            I have read and agree to this
                                            Privacy Notice
                                        </Label>
                                    </div>
                                    <InputError message={errors.agree} />
                                </div>

                                <div className="flex flex-wrap items-center justify-end gap-2 sm:shrink-0 sm:gap-3">
                                    {!agreed && (
                                        <span className="text-xs text-muted-foreground max-sm:basis-full max-sm:text-left">
                                            Check the box to continue
                                        </span>
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        disabled={processing || declining}
                                        onClick={decline}
                                        data-test="privacy-decline-button"
                                    >
                                        {declining && <Spinner />}
                                        Decline and log out
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={
                                            !agreed || processing || declining
                                        }
                                        data-test="privacy-accept-button"
                                    >
                                        {processing && <Spinner />}I Accept
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </Form>

            <Toaster />
            <IdleTimeoutDialog />
        </>
    );
}
