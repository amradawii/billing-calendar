<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * Deterministic simulation of a time zone rules change: most of Mexico (America/Mexico_City) abolished daylight
 * saving time in October 2022. It does not depend on the tz database bundled with PHP, which may predate or
 * postdate the change.
 *
 *  - Old rules: UTC-06:00, with summer time UTC-05:00 from the first Sunday of April 02:00 to the last Sunday of
 *    October 02:00.
 *  - New rules: UTC-06:00 all year.
 *
 * The naive side expands the schedule up front under the old rules and stores UTC instants, which are then read
 * under the new rules. The correct side keeps the wall time and expands it under the new rules.
 */
final class MexicoRulesChange
{
    public const ZONE = 'America/Mexico_City';

    private const STANDARD_OFFSET = -6 * 3600;

    private const SUMMER_OFFSET = -5 * 3600;

    public function __construct(
        private readonly CorrectCalculator $calculator = new CorrectCalculator(),
    ) {
    }

    /**
     * @return list<Occurrence>
     */
    public function naive(RecurrenceRule $rule, int $count): array
    {
        return array_map(function (Occurrence $occurrence) {
            $wall = $occurrence->local->format('Y-m-d H:i');
            $storedUtc = CarbonImmutable::createFromFormat('!Y-m-d H:i', $wall, 'UTC')->subSeconds($this->oldOffset($wall));

            return new Occurrence($occurrence->index, $storedUtc->setTimezone($this->newRules()));
        }, $this->wallTimes($rule, $count));
    }

    /**
     * @return list<Occurrence>
     */
    public function correct(RecurrenceRule $rule, int $count): array
    {
        return array_map(function (Occurrence $occurrence) {
            $wall = $occurrence->local->format('Y-m-d H:i');
            if ($this->oldOffset($wall) === self::STANDARD_OFFSET) {
                return $occurrence;
            }

            return new Occurrence($occurrence->index, $occurrence->local, [...$occurrence->notes, sprintf(
                'Summer time (UTC-05:00) under the old rules; under the new rules %s is UTC-06:00 (%s UTC)',
                $occurrence->local->format('H:i'), $occurrence->utc->format('H:i'),
            )]);
        }, $this->wallTimes($rule, $count));
    }

    /**
     * @return list<Occurrence>
     */
    private function wallTimes(RecurrenceRule $rule, int $count): array
    {
        return $this->calculator->occurrences($rule, $count, $this->newRules());
    }

    private function newRules(): DateTimeZone
    {
        return new DateTimeZone('-06:00');
    }

    private function oldOffset(string $wall): int
    {
        $year = (int) substr($wall, 0, 4);
        $summerStarts = CarbonImmutable::create($year, 4, 1, 0, 0, 0, 'UTC')->firstOfMonth(CarbonImmutable::SUNDAY)->format('Y-m-d').' 02:00';
        $summerEnds = CarbonImmutable::create($year, 10, 1, 0, 0, 0, 'UTC')->lastOfMonth(CarbonImmutable::SUNDAY)->format('Y-m-d').' 02:00';

        return $wall >= $summerStarts && $wall < $summerEnds ? self::SUMMER_OFFSET : self::STANDARD_OFFSET;
    }
}
