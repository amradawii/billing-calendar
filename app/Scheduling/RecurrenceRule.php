<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * "Starting on this date, every N days/weeks/months/years, at this wall-clock time in this time zone."
 */
final class RecurrenceRule
{
    /**
     * The anchor date. Calendar dates are kept at midnight UTC so that date arithmetic never touches DST;
     * the wall time and zone are applied per occurrence.
     */
    public readonly CarbonImmutable $startDate;

    public function __construct(
        string $startDate,
        public readonly Frequency $frequency,
        public readonly int $interval = 1,
        public readonly string $localTime = '09:00',
        public readonly string $timeZone = 'UTC',
    ) {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})\z/', $startDate, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new InvalidArgumentException("Invalid start date \"{$startDate}\", expected YYYY-MM-DD.");
        }

        if ($interval < 1) {
            throw new InvalidArgumentException("Interval must be at least 1, got {$interval}.");
        }

        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d\z/', $localTime)) {
            throw new InvalidArgumentException("Invalid local time \"{$localTime}\", expected HH:MM (00:00-23:59).");
        }

        if (! in_array($timeZone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("Unknown time zone \"{$timeZone}\", expected an IANA name such as Europe/Berlin.");
        }

        $this->startDate = CarbonImmutable::createFromFormat('!Y-m-d', $startDate, 'UTC');
    }
}
