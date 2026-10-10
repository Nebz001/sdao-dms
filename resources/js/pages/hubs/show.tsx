import { Head } from '@inertiajs/react';
import HubPage from '@/components/hub-page';
import type { HubChip } from '@/components/hub-page';
import { HUB_GROUP } from '@/lib/hub-options';
import type { HubKey } from '@/lib/hub-options';

type Props = {
    hub: HubKey;
    organizationName: string | null;
    chips: Record<string, HubChip>;
};

export default function HubShow({ hub, organizationName, chips }: Props) {
    return (
        <>
            <Head title={HUB_GROUP[hub]} />
            <HubPage
                hub={hub}
                organizationName={organizationName}
                chips={chips}
            />
        </>
    );
}
