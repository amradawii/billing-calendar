<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Computes occurrence n from the original start date and n, never from the previous occurrence.
 *
 * Monthly and yearly occurrences keep the anchor day (the start date's day of month). When the target month is
 * too short, the date is clamped to its last day for that occurrence only, so a Jan 31 plan renews on
 * Feb 28, Mar 31, Apr 30, May 31, ... and a Feb 29 yearly plan renews on Feb 28 in common years and Feb 29 in leap years.
 *
 * The wall time is then resolved in the zone for each date separately, so 09:00 stays 09:00 local across DST
 * changes, and DST gaps and overlaps are settled by the rule's policies.
 */
final class CorrectCalculator
{
    public function __construct(
        private readonly LocalTimeResolver $resolver = new LocalTimeResolver(),
    ) {
    }

    /**
     * @param  DateTimeZone|null  $zoneRules  rules to resolve wall times with instead of the rule's IANA zone
     *                                        (used by the rules-change simulation)
     * @return list<Occurrence>
     */
    public function occurrences(RecurrenceRule $rule, int $count, ?DateTimeZone $zoneRules = null): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException("Count must be at least 1, got {$count}.");
        }

        $zone = $zoneRules ?? new DateTimeZone($rule->timeZone);

        $occurrences = [];
        for ($n = 0; $n < $count; $n++) {
            [$date, $notes] = $this->date($rule, $n);

            $time = $this->resolver->resolve($date->format('Y-m-d'), $rule->localTime, $zone, $rule->gapPolicy, $rule->overlapPolicy);

            $occurrences[] = new Occurrence($n, $time->local, $time->note === null ? $notes : [...$notes, $time->note]);
        }

        return $occurrences;
    }

    /**
     * @return array{0: CarbonImmutable, 1: list<string>}
     */
    private function date(RecurrenceRule $rule, int $n): array
    {
        $start = $rule->startDate;
        $steps = $n * $rule->interval;

        return match ($rule->frequency) {
            Frequency::DAILY => [$start->addDays($steps), []],
            Frequency::WEEKLY => [$start->addWeeks($steps), []],
            Frequency::MONTHLY => $this->onAnchorDay($rule, $start->startOfMonth()->addMonths($steps)),
            Frequency::YEARLY => $this->onAnchorDay($rule, $start->startOfMonth()->addYears($steps)),
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: list<string>}
     */
    private function onAnchorDay(RecurrenceRule $rule, CarbonImmutable $firstOfMonth): array
    {
        $anchorDay = $rule->startDate->day;

        if ($anchorDay <= $firstOfMonth->daysInMonth) {
            return [$firstOfMonth->setDay($anchorDay), []];
        }

        $date = $firstOfMonth->setDay($firstOfMonth->daysInMonth);

        $note = $rule->frequency === Frequency::YEARLY
            ? "{$date->year} is not a leap year: Feb 29 clamped to Feb 28"
            : "{$date->format('F Y')} has no {$this->ordinal($anchorDay)}: clamped to {$date->format('M j')}";

        return [$date, [$note]];
    }

    private function ordinal(int $day): string
    {
        return match ($day) {
            29 => '29th',
            30 => '30th',
            31 => '31st',
        };
    }
}
