<?php

use App\Scheduling\Frequency;
use App\Scheduling\NaiveCalculator;
use App\Scheduling\RecurrenceRule;

function naiveAt(string $start, Frequency $frequency, int $count, string $time, string $zone): array
{
    return (new NaiveCalculator())->occurrences(new RecurrenceRule($start, $frequency, 1, $time, $zone), $count);
}

it('moves "every day at 09:00" to 08:00 local after the clocks go back, because it adds 86 400 s', function () {
    expect(times(naiveAt('2022-10-28', Frequency::DAILY, 4, '09:00', 'Europe/Berlin')))->toBe([
        '2022-10-28 09:00 +02:00',
        '2022-10-29 09:00 +02:00',
        '2022-10-30 08:00 +01:00',
        '2022-10-31 08:00 +01:00',
    ]);
});

it('moves "every day at 09:00" to 10:00 local after the clocks go forward', function () {
    expect(times(naiveAt('2022-03-25', Frequency::DAILY, 4, '09:00', 'Europe/Berlin')))->toBe([
        '2022-03-25 09:00 +01:00',
        '2022-03-26 09:00 +01:00',
        '2022-03-27 10:00 +02:00',
        '2022-03-28 10:00 +02:00',
    ]);
});

it('keeps a reminder that fell in the gap shifted for good', function () {
    expect(times(naiveAt('2022-03-26', Frequency::DAILY, 3, '02:30', 'Europe/Berlin')))->toBe([
        '2022-03-26 02:30 +01:00',
        '2022-03-27 03:30 +02:00',
        '2022-03-28 03:30 +02:00',
    ]);
});

it('sends the 01:30 reminder at 00:30 after the fall-back night', function () {
    expect(times(naiveAt('2022-11-05', Frequency::DAILY, 3, '01:30', 'America/New_York')))->toBe([
        '2022-11-05 01:30 -04:00',
        '2022-11-06 01:30 -04:00',
        '2022-11-07 00:30 -05:00',
    ]);
});

it('combines the month overflow with the DST drift', function () {
    expect(times(naiveAt('2022-01-31', Frequency::MONTHLY, 3, '09:00', 'Europe/Berlin')))->toBe([
        '2022-01-31 09:00 +01:00',
        '2022-03-03 09:00 +01:00',
        '2022-04-03 10:00 +02:00',
    ]);
});

it('is right by luck in a zone without DST', function () {
    expect(array_unique(array_map(fn ($o) => $o->local->format('H:i'), naiveAt('2022-03-20', Frequency::DAILY, 30, '09:00', 'Asia/Tokyo'))))
        ->toBe(['09:00']);
});
