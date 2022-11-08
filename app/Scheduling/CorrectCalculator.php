<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Computes occurrence n from the original start date and n, never from the previous occurrence.
 *
 * Monthly and yearly occurrences keep the anchor day (the start date's day of month). When the target month is
 * too short, the date is clamped to its last day for that occurrence only, so a Jan 31 plan renews on
 * Feb 28, Mar 31, Apr 30, May 31, ... and a Feb 29 yearly plan renews on Feb 28 in common years and Feb 29 in leap years.
 */
final class CorrectCalculator
{
    /**
     * @return list<Occurrence>
     */
    public function occurrences(RecurrenceRule $rule, int $count): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException("Count must be at least 1, got {$count}.");
        }

        $occurrences = [];
        for ($n = 0; $n < $count; $n++) {
            $occurrences[] = $this->occurrence($rule, $n);
        }

        return $occurrences;
    }

    private function occurrence(RecurrenceRule $rule, int $n): Occurrence
    {
        $start = $rule->startDate;
        $steps = $n * $rule->interval;

        return match ($rule->frequency) {
            Frequency::DAILY => new Occurrence($n, $start->addDays($steps)),
            Frequency::WEEKLY => new Occurrence($n, $start->addWeeks($steps)),
            Frequency::MONTHLY => $this->onAnchorDay($n, $rule, $start->startOfMonth()->addMonths($steps)),
            Frequency::YEARLY => $this->onAnchorDay($n, $rule, $start->startOfMonth()->addYears($steps)),
        };
    }

    private function onAnchorDay(int $n, RecurrenceRule $rule, CarbonImmutable $firstOfMonth): Occurrence
    {
        $anchorDay = $rule->startDate->day;

        if ($anchorDay <= $firstOfMonth->daysInMonth) {
            return new Occurrence($n, $firstOfMonth->setDay($anchorDay));
        }

        $date = $firstOfMonth->setDay($firstOfMonth->daysInMonth);

        $note = $rule->frequency === Frequency::YEARLY
            ? "{$date->year} is not a leap year: Feb 29 clamped to Feb 28"
            : "{$date->format('F Y')} has no {$this->ordinal($anchorDay)}: clamped to {$date->format('M j')}";

        return new Occurrence($n, $date, [$note]);
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
