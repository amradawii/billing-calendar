<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Turns a wall-clock time on a date ("09:00 on 2022-03-27") into one instant in a zone.
 *
 * A wall time W in a zone corresponds to the instant W - offset, for every offset the zone uses around that day,
 * as long as the zone really uses that offset at that instant. One match is the normal case; none means W falls in
 * a DST gap; two means W falls in a DST overlap. Gaps and overlaps are then settled by an explicit policy, and the
 * decision is explained in a note. Works with any DateTimeZone, including fixed offsets such as "-06:00".
 */
final class LocalTimeResolver
{
    public function resolve(
        string $date,
        string $time,
        DateTimeZone $zone,
        GapPolicy $gapPolicy = GapPolicy::SHIFT_FORWARD,
        OverlapPolicy $overlapPolicy = OverlapPolicy::EARLIER,
    ): ResolvedTime {
        $wall = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$time}", 'UTC')->getTimestamp();

        // Offsets in force a day before and a day after: different only when the clocks change around this day.
        $before = $this->offsetAt($zone, $wall - 86400);
        $after = $this->offsetAt($zone, $wall + 86400);

        $candidates = [];
        foreach (array_unique([$before, $after]) as $offset) {
            if ($this->offsetAt($zone, $wall - $offset) === $offset) {
                $candidates[] = $wall - $offset;
            }
        }
        sort($candidates);

        if (count($candidates) === 1) {
            return new ResolvedTime($this->instant($candidates[0], $zone));
        }

        if (count($candidates) === 2) {
            $change = $this->firstInstantWithOffset($zone, $candidates[0], $candidates[1], $after);
            $instant = $this->instant($overlapPolicy === OverlapPolicy::EARLIER ? $candidates[0] : $candidates[1], $zone);

            return new ResolvedTime($instant, sprintf(
                '%s happens twice on %s in %s (clocks go back from %s to %s): using the %s one, UTC%s',
                $time, $date, $zone->getName(), $this->clock($change, $before), $this->clock($change, $after),
                $overlapPolicy === OverlapPolicy::EARLIER ? 'first' : 'second', $instant->format('P'),
            ));
        }

        $change = $this->firstInstantWithOffset($zone, $wall - $after, $wall - $before, $after);
        $description = sprintf(
            '%s does not exist on %s in %s (clocks jump from %s to %s)',
            $time, $date, $zone->getName(), $this->clock($change, $before), $this->clock($change, $after),
        );

        if ($gapPolicy === GapPolicy::SHIFT_FORWARD) {
            $instant = $this->instant($wall - $before, $zone);

            return new ResolvedTime($instant, sprintf(
                '%s: shifted forward %s to %s', $description, $this->duration($after - $before), $instant->format('H:i'),
            ));
        }

        $instant = $this->instant($change, $zone);

        return new ResolvedTime($instant, "{$description}: moved to {$instant->format('H:i')}, the first valid minute");
    }

    private function offsetAt(DateTimeZone $zone, int $timestamp): int
    {
        return $zone->getOffset(new DateTimeImmutable("@{$timestamp}"));
    }

    /**
     * Binary search for the moment the clocks change: the first timestamp in [$low, $high] using $offset.
     */
    private function firstInstantWithOffset(DateTimeZone $zone, int $low, int $high, int $offset): int
    {
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            if ($this->offsetAt($zone, $middle) === $offset) {
                $high = $middle;
            } else {
                $low = $middle + 1;
            }
        }

        return $low;
    }

    private function instant(int $timestamp, DateTimeZone $zone): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($timestamp, $zone);
    }

    private function clock(int $timestamp, int $offset): string
    {
        return gmdate('H:i', $timestamp + $offset);
    }

    private function duration(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);

        if ($minutes % 60 === 0) {
            return $minutes === 60 ? '1 hour' : ($minutes / 60).' hours';
        }

        return "{$minutes} minutes";
    }
}
