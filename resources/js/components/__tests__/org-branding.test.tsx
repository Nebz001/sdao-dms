import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import OrgBranding from '@/components/org-branding';
import type { AuthOrganization } from '@/types/auth';

function org(overrides: Partial<AuthOrganization> = {}): AuthOrganization {
    return {
        id: 1,
        name: 'Computing Society',
        logoUrl: null,
        school: null,
        ...overrides,
    };
}

describe('OrgBranding', () => {
    it('renders the organization name', () => {
        render(<OrgBranding organization={org({ name: 'Computing Society' })} />);

        expect(screen.getByText('Computing Society')).toBeInTheDocument();
    });

    it('falls back to the first two letters for a single-word org name — not the single-letter useInitials() behavior', () => {
        render(<OrgBranding organization={org({ name: 'CODECS' })} />);

        expect(screen.getByText('CO')).toBeInTheDocument();
    });

    it('falls back to first+last initials for a multi-word org name, same as useInitials()', () => {
        render(<OrgBranding organization={org({ name: 'Red Cross Youth' })} />);

        expect(screen.getByText('RY')).toBeInTheDocument();
    });
});
