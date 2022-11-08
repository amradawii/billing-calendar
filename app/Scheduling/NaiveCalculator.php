<?php

namespace App\Scheduling;

use InvalidArgumentException;

/**
 * DELIBERATELY BROKEN: for side-by-side comparison only. Never use it to schedule anything.
 *
 * This is the way renewal dates are usually computed: every date is derived from the previous one using Carbon's
 * overflowing month and year arithmetic (what addMonth() and addYear() do by default). The mistakes it makes:
 *
 *  - Jan 31 + 1 month overflows into March (Mar 3 in 2022) and every later renewal stays on the 3rd.
 *  - Feb 29 + 1 year overflows to Mar 1 and never comes back to Feb 29.
 *
 * The other common variant, addMonthNoOverflow() chained from the previous date, goes Jan 31 -> Feb 28 -> Mar 28
 * and then stays on the 28th forever.
 */
final class NaiveCalculator
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
        $date = $rule->startDate;

        for ($n = 0; $n < $count; $n++) {
            $occurrences[] = new Occurrence($n, $date);

            $date = match ($rule->frequency) {
                Frequency::DAILY => $date->addDays($rule->interval),
                Frequency::WEEKLY => $date->addWeeks($rule->interval),
                Frequency::MONTHLY => $date->addMonthsWithOverflow($rule->interval),
                Frequency::YEARLY => $date->addYearsWithOverflow($rule->interval),
            };
        }

        return $occurrences;
    }
}
