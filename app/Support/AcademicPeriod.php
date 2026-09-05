<?php

namespace App\Support;

use App\Enums\Term;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

/**
 * A single (academic year, term) point in time — the unit the admin-controlled
 * "current period" setting (App\Support\CurrentPeriod) advances one at a time,
 * and the unit organization renewal seasons and coverage are measured in.
 *
 * Comparison is over the integer tuple [startYear, term->order()], never over
 * the canonical string — see isAfter()/isBefore(). The canonical string form
 * happens to also sort correctly lexicographically (fixed-width year prefix,
 * "first" < "second" < "third"), but that is not something to build on; every
 * ordering comparison in this codebase happens in PHP over a resolved value,
 * not in SQL.
 */
final readonly class AcademicPeriod
{
    public function __construct(
        public string $academicYear,
        public Term $term,
    ) {}

    /**
     * Which term a calendar month falls into, for deriving a period from a
     * historical date (forDate()) when no stamped period exists.
     *
     * PROVISIONAL — Aug-Nov/Dec-Mar/Apr-Jul is an even three-way split of the
     * existing August academic-year rollover, not yet confirmed against NU
     * Lipa's real term calendar. Kept in exactly one place, unit-tested in
     * tests/Unit/AcademicPeriodTest.php, so correcting it later is a one-line
     * change plus a re-run of the historical-data backfill migration.
     *
     * @var array<int, Term>
     */
    private const array MONTH_TO_TERM = [
        1 => Term::SecondTerm, 2 => Term::SecondTerm, 3 => Term::SecondTerm,
        4 => Term::ThirdTerm, 5 => Term::ThirdTerm, 6 => Term::ThirdTerm, 7 => Term::ThirdTerm,
        8 => Term::FirstTerm, 9 => Term::FirstTerm, 10 => Term::FirstTerm, 11 => Term::FirstTerm,
        12 => Term::SecondTerm,
    ];

    public static function fromString(string $value): self
    {
        $period = self::tryFromString($value);

        if ($period === null) {
            throw new InvalidArgumentException("Invalid academic period string: {$value}");
        }

        return $period;
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $parts = explode(':', $value, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$academicYear, $termValue] = $parts;
        $term = Term::tryFrom($termValue);

        if ($term === null || ! preg_match('/^\d{4}-\d{4}$/', $academicYear)) {
            return null;
        }

        return new self($academicYear, $term);
    }

    /**
     * Derives a period from a calendar date via MONTH_TO_TERM. Used for
     * historical documents that predate the stored current-period setting,
     * and by the backfill migration.
     */
    public static function forDate(CarbonInterface $date): self
    {
        return new self(AcademicYear::forDate($date), self::MONTH_TO_TERM[$date->month]);
    }

    public function toString(): string
    {
        return "{$this->academicYear}:{$this->term->value}";
    }

    public function label(): string
    {
        return "{$this->term->label()}, {$this->academicYear}";
    }

    public function startYear(): int
    {
        return (int) explode('-', $this->academicYear)[0];
    }

    public function equals(self $other): bool
    {
        return $this->academicYear === $other->academicYear && $this->term === $other->term;
    }

    public function isAfter(self $other): bool
    {
        return [$this->startYear(), $this->term->order()] > [$other->startYear(), $other->term->order()];
    }

    public function isBefore(self $other): bool
    {
        return [$this->startYear(), $this->term->order()] < [$other->startYear(), $other->term->order()];
    }

    /**
     * The next period in sequence — wraps 3rd term into 1st term of the
     * following academic year. Drives the settings screen's year auto-suggest.
     */
    public function next(): self
    {
        if ($this->term === Term::ThirdTerm) {
            return new self($this->nextAcademicYear(), Term::FirstTerm);
        }

        return new self($this->academicYear, $this->term->next());
    }

    /**
     * True only for 3rd term — the single named place the renewal-season rule
     * is stated, so it stays greppable rather than re-derived at call sites.
     */
    public function isRenewalSeason(): bool
    {
        return $this->term === Term::ThirdTerm;
    }

    /**
     * The academic year a renewal filed during this period's 3rd-term season
     * would cover, e.g. "2026-2027" -> "2027-2028".
     */
    public function nextAcademicYear(): string
    {
        $startYear = $this->startYear();

        return ($startYear + 1).'-'.($startYear + 2);
    }

    /**
     * [start, end) for this period's academic year — the same half-open
     * August 1 window AcademicYear::currentRange() used to compute from the
     * wall clock, now reconstructed from the stored year.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function academicYearRange(): array
    {
        $startYear = $this->startYear();

        return [
            Date::create($startYear, 8, 1)->startOfDay(),
            Date::create($startYear + 1, 8, 1)->startOfDay(),
        ];
    }
}
