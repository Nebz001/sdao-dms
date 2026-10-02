import { describe, expect, it } from 'vitest';
import {
    ALL_VENUES,
    filterBookings,
    groupByDate,
    selectComingUp,
} from '@/lib/venue-calendar';
import type { VenueBooking } from '@/types/venue-calendar';

let nextId = 1;

function booking(overrides: Partial<VenueBooking>): VenueBooking {
    return {
        id: nextId++,
        name: 'Event',
        venue: 'Gymnasium',
        activity_date: '2026-10-10',
        start_time: '09:00',
        end_time: '11:00',
        status: 'approved',
        organization: 'CODECS',
        document_id: 1,
        ...overrides,
    };
}

const noFilter = {
    scope: 'all' as const,
    ownOrganization: null,
    organization: null,
    venue: ALL_VENUES,
};

describe('filterBookings', () => {
    const all = [
        booking({ organization: 'CODECS', venue: 'Gymnasium' }),
        booking({ organization: 'UAPSA', venue: 'Gymnasium' }),
        booking({ organization: 'UAPSA', venue: 'Room 201' }),
    ];

    it('returns everything with no filter', () => {
        expect(filterBookings(all, noFilter)).toHaveLength(3);
    });

    it('keeps only the own organization for the "mine" scope', () => {
        const result = filterBookings(all, {
            ...noFilter,
            scope: 'mine',
            ownOrganization: 'UAPSA',
        });

        expect(result.map((b) => b.organization)).toEqual(['UAPSA', 'UAPSA']);
    });

    it('applies the organization and venue filters together', () => {
        const result = filterBookings(all, {
            ...noFilter,
            organization: 'UAPSA',
            venue: 'Room 201',
        });

        expect(result).toHaveLength(1);
    });
});

describe('groupByDate', () => {
    it('buckets bookings by date', () => {
        const grouped = groupByDate([
            booking({ activity_date: '2026-10-10' }),
            booking({ activity_date: '2026-10-10' }),
            booking({ activity_date: '2026-10-12' }),
        ]);

        expect(grouped['2026-10-10']).toHaveLength(2);
        expect(grouped['2026-10-12']).toHaveLength(1);
    });
});

describe('selectComingUp', () => {
    const today = '2026-10-02';

    it('skips past bookings and sorts by date then start time', () => {
        const late = booking({
            activity_date: '2026-10-05',
            start_time: '13:00',
        });
        const early = booking({
            activity_date: '2026-10-05',
            start_time: '08:00',
        });
        const past = booking({ activity_date: '2026-09-30' });

        const { items } = selectComingUp([late, past, early], {
            today,
            selectedDate: null,
        });

        expect(items).toEqual([early, late]);
    });

    it('removes the selected day before applying the limit', () => {
        const onSelected = Array.from({ length: 3 }, () =>
            booking({ activity_date: '2026-10-05' }),
        );
        const others = Array.from({ length: 4 }, (_, i) =>
            booking({ activity_date: `2026-10-1${i}` }),
        );

        const result = selectComingUp([...onSelected, ...others], {
            today,
            selectedDate: '2026-10-05',
            limit: 3,
        });

        expect(result.items).toHaveLength(3);
        expect(result.items.some((b) => b.activity_date === '2026-10-05')).toBe(
            false,
        );
        expect(result.hasMore).toBe(true);
        expect(result.hiddenOnSelectedDay).toBe(3);
    });

    it('reports no more when exactly the limit remains, ignoring the selected day', () => {
        const result = selectComingUp(
            [
                booking({ activity_date: '2026-10-05' }),
                booking({ activity_date: '2026-10-06' }),
                booking({ activity_date: '2026-10-07' }),
            ],
            { today, selectedDate: '2026-10-05', limit: 2 },
        );

        expect(result.items).toHaveLength(2);
        expect(result.hasMore).toBe(false);
    });

    it('is unchanged when the selected day has no bookings', () => {
        const list = [
            booking({ activity_date: '2026-10-05' }),
            booking({ activity_date: '2026-10-06' }),
        ];

        const result = selectComingUp(list, {
            today,
            selectedDate: '2026-10-20',
        });

        expect(result.items).toHaveLength(2);
        expect(result.hiddenOnSelectedDay).toBe(0);
    });

    it('reports hidden bookings when everything upcoming is on the selected day', () => {
        const result = selectComingUp(
            [booking({ activity_date: '2026-10-05' })],
            {
                today,
                selectedDate: '2026-10-05',
            },
        );

        expect(result.items).toEqual([]);
        expect(result.hiddenOnSelectedDay).toBe(1);
    });

    it('includes today when no day is selected', () => {
        const { items } = selectComingUp([booking({ activity_date: today })], {
            today,
            selectedDate: null,
        });

        expect(items).toHaveLength(1);
    });
});
