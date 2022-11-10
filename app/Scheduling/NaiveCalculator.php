<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * DELIBERATELY BROKEN: for side-by-side comparison only. Never use it to schedule anything.
 *
 * This is the way renewal dates and reminders are usually computed: the first reminder is converted to a UTC
 * instant, and every later one is derived from the previous instant, in UTC, with Carbon's overflowing month and
 * year arithmetic (what addMonth() and addYear() do by default) or by adding 86 400 seconds per day. The mistakes it
 * makes:
 *
 *  - Jan 31 + 1 month overflows into March (Mar 3 in 2022) and every later renewal stays on the 3rd.
 *  - Feb 29 + 1 year overflows to Mar 1 and never comes back to Feb 29.
 *  - "Every day at 09:00" as +86 400 s moves to 10:00 or 08:00 local after each DST change.
 *  - A reminder that fell in a DST gap stays shifted (02:30 becomes 03:30 for good).
 *  - The UTC instants bake in the zone's rules at the time of computing, so later rule changes are missed.
 *
 * The other common variant, addMonthNoOverflow() chained from the previous date, goes Jan 31 -> Feb 28 -> Mar 28
 * and then stays on the 28th forever.
 */
final class NaiveCalculator
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * @return list<Occurrence>
     */
    public function occurrences(RecurrenceRule $rule, int $count): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException("Count must be at least 1, got {$count}.");
        }

        $utc = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i', "{$rule->startDate->format('Y-m-d')} {$rule->localTime}", $rule->timeZone,
        )->utc();

        $occurrences = [];
        for ($n = 0; $n < $count; $n++) {
            $occurrences[] = new Occurrence($n, $utc->setTimezone($rule->timeZone));

            $utc = match ($rule->frequency) {
                Frequency::DAILY => $utc->addSeconds($rule->interval * self::SECONDS_PER_DAY),
                Frequency::WEEKLY => $utc->addSeconds($rule->interval * 7 * self::SECONDS_PER_DAY),
                Frequency::MONTHLY => $utc->addMonthsWithOverflow($rule->interval),
                Frequency::YEARLY => $utc->addYearsWithOverflow($rule->interval),
            };
        }

        return $occurrences;
    }
}
