import AccountName from '@/components/account-name';
import { NO_SCHOOL_LABEL } from '@/lib/school';
import { DetailField, DetailSection, DetailText, DetailsCard } from './details';
import { formatLongDate, formatPhone } from './format';

export type OrganizationDetail = {
    organization_type: string;
    organization_type_label: string;
    purpose_of_organization: string;
    contact_person: string;
    contact_no: string;
    email_address: string;
    date_organized: string | null;
    adviser: { name: string } | null;
    /** Renewals only: the academic year the filing covers. */
    academic_year?: string | null;
} | null;

export type OrganizationSummary = {
    name: string;
    college: string | null;
    program: string | null;
};

/**
 * The details card for organization registrations and renewals, which share
 * the same fields. Registration and renewal pages, student and reviewer, all
 * render this one component.
 */
export default function OrganizationDetails({
    title,
    organization,
    detail,
}: {
    title: string;
    organization: OrganizationSummary;
    detail: OrganizationDetail;
}) {
    return (
        <DetailsCard title={title}>
            <DetailSection label="Organization">
                <DetailField label="Organization name">
                    {organization.name}
                </DetailField>
                <DetailField label="Type of organization">
                    {detail?.organization_type_label}
                </DetailField>
                <DetailField label="College">
                    {organization.college ?? (
                        <span className="font-normal text-muted-foreground">
                            {NO_SCHOOL_LABEL}
                        </span>
                    )}
                </DetailField>
                <DetailField label="Program" hideIfEmpty>
                    {organization.program}
                </DetailField>
                {detail?.academic_year !== undefined && (
                    <DetailField label="Academic year">
                        {detail.academic_year}
                    </DetailField>
                )}
                <DetailField label="Date organized">
                    {formatLongDate(detail?.date_organized)}
                </DetailField>
                <DetailField label="Adviser">
                    {detail?.adviser?.name ? (
                        <AccountName
                            name={detail.adviser.name}
                            nameClassName="font-normal"
                        />
                    ) : null}
                </DetailField>
            </DetailSection>
            <DetailSection label="Contact">
                <DetailField label="Contact person">
                    {detail?.contact_person}
                </DetailField>
                <DetailField label="Contact number">
                    {formatPhone(detail?.contact_no)}
                </DetailField>
                <DetailField label="Email address" wide>
                    {detail?.email_address}
                </DetailField>
            </DetailSection>
            <DetailText label="Purpose of organization">
                {detail?.purpose_of_organization}
            </DetailText>
        </DetailsCard>
    );
}
