<?php

namespace App;

/**
 * The preset tabs of the page: each one explains an edge case and fills the form with a schedule that shows it.
 */
final class Scenarios
{
    /**
     * @return list<array{id: string, label: string, problem: ?string, solution: ?string, params: array<string, string|int>}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'month-end',
                'label' => 'Month-end anchor',
                'problem' => 'A customer signs up on January 31 and pays monthly. The usual code adds one month to the previous renewal: '
                    .'Jan 31 + 1 month overflows to March 3, February is skipped, and every later renewal stays on the 3rd. '
                    .'The no-overflow variant lands on Feb 28 and then drifts to the 28th for good.',
                'solution' => 'Every renewal is computed from the original start date: start + n months. If the month has no 31st, '
                    .'the date is clamped to its last day for that month only, so the plan renews on Feb 28, Mar 31, Apr 30, May 31 '
                    .'and stays anchored to the 31st.',
                'params' => self::params('2022-01-31', 'monthly', '09:00', 'UTC', 12),
            ],
            [
                'id' => 'leap-day',
                'label' => 'Leap day',
                'problem' => 'A yearly plan starts on Feb 29, 2020. Adding a year overflows to Mar 1, 2021, and because the next renewal '
                    .'is computed from Mar 1, it never comes back to Feb 29, not even in the leap year 2024.',
                'solution' => 'Yearly renewals are start + n years with a Feb 29 rule: clamped to Feb 28 in common years, '
                    .'back on Feb 29 in leap years.',
                'params' => self::params('2020-02-29', 'yearly', '09:00', 'UTC', 6),
            ],
            [
                'id' => 'dst-spring',
                'label' => 'DST spring forward',
                'problem' => 'A reminder is set for 02:30 every day in Berlin. On 2022-03-27 the clocks jump from 02:00 to 03:00, '
                    .'so 02:30 does not exist that night. The usual code adds 24 hours to the previous reminder in UTC: '
                    .'it lands at 03:30 that night and stays at 03:30 every day after.',
                'solution' => 'Each reminder is computed from the wall time 02:30 and the zone, separately for each day. When the time '
                    .'does not exist, an explicit gap policy decides: shift forward by the length of the gap (03:30, the default) '
                    .'or use the first valid minute (03:00). The next day is back at 02:30.',
                'params' => self::params('2022-03-25', 'daily', '02:30', 'Europe/Berlin', 7),
            ],
            [
                'id' => 'dst-fall',
                'label' => 'DST fall back',
                'problem' => 'A reminder is set for 01:30 every day in New York. On 2022-11-06 the clocks go back from 02:00 to 01:00, '
                    .'so 01:30 happens twice. Code that watches the wall clock can send it twice; code that adds 24 hours in UTC '
                    .'sends it at 00:30 from the next day on.',
                'solution' => 'An explicit overlap policy picks exactly one of the two 01:30s: the first (UTC-04:00, the default) or the '
                    .'second (UTC-05:00). It is never sent twice, and the next day is at 01:30 again.',
                'params' => self::params('2022-11-03', 'daily', '01:30', 'America/New_York', 7),
            ],
            [
                'id' => 'daily-0900',
                'label' => 'Daily 09:00 across DST',
                'problem' => '"Every day at 09:00" is often implemented as the previous reminder + 86 400 seconds. That keeps the UTC '
                    .'time fixed, so when Berlin goes back to winter time on 2022-10-30, every reminder moves to 08:00 local '
                    .'(and to 10:00 after the spring change).',
                'solution' => 'The schedule stores 09:00 and Europe/Berlin, never a UTC instant. Each occurrence is converted to UTC '
                    .'when it is computed, so it is always 09:00 local: 07:00 UTC in summer, 08:00 UTC in winter.',
                'params' => self::params('2022-10-27', 'daily', '09:00', 'Europe/Berlin', 8),
            ],
            [
                'id' => 'rules-change',
                'label' => 'Rules change (Mexico 2022)',
                'problem' => 'Most of Mexico abolished daylight saving time in October 2022. Reminders that were expanded in advance and '
                    .'stored as UTC instants still follow the old rules: every one that falls in the former summer-time window '
                    .'(2023-04-02 to 2023-10-29) now fires at 08:00 instead of 09:00.',
                'solution' => 'The schedule stores the wall time and the zone, and expands occurrences only when they are needed, so they '
                    .'follow the rules in force. Simulated with two fixed rule sets (old: UTC-06:00 with summer time UTC-05:00; '
                    .'new: UTC-06:00 all year), so the result does not depend on the installed time zone database.',
                'params' => [...self::params('2022-11-15', 'monthly', '09:00', 'America/Mexico_City', 12), 'scenario' => 'mexico-2022'],
            ],
            [
                'id' => 'custom',
                'label' => 'Custom',
                'problem' => null,
                'solution' => null,
                'params' => self::params('2022-11-30', 'monthly', '09:00', 'Europe/Berlin', 12),
            ],
        ];
    }

    /**
     * @return array<string, string|int>
     */
    private static function params(string $start, string $frequency, string $time, string $zone, int $count): array
    {
        return [
            'start' => $start,
            'frequency' => $frequency,
            'interval' => 1,
            'time' => $time,
            'zone' => $zone,
            'gap' => 'shift_forward',
            'overlap' => 'earlier',
            'count' => $count,
        ];
    }
}
