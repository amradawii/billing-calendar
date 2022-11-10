<?php

use App\Scheduling\CorrectCalculator;
use App\Scheduling\Frequency;
use App\Scheduling\GapPolicy;
use App\Scheduling\OverlapPolicy;
use App\Scheduling\RecurrenceRule;

function correctAt(string $start, Frequency $frequency, int $count, string $time, string $zone, GapPolicy $gap = GapPolicy::SHIFT_FORWARD, OverlapPolicy $overlap = OverlapPolicy::EARLIER): array
{
    return (new CorrectCalculator())->occurrences(new RecurrenceRule($start, $frequency, 1, $time, $zone, $gap, $overlap), $count);
}

it('shifts only the 02:30 that falls in the spring-forward gap (Europe/Berlin)', function (GapPolicy $policy, string $onGapDay) {
    $occurrences = correctAt('2022-03-25', Frequency::DAILY, 5, '02:30', 'Europe/Berlin', $policy);

    expect(times($occurrences))->toBe([
        '2022-03-25 02:30 +01:00',
        '2022-03-26 02:30 +01:00',
        "2022-03-27 {$onGapDay} +02:00",
        '2022-03-28 02:30 +02:00',
        '2022-03-29 02:30 +02:00',
    ])
        ->and($occurrences[2]->notes)->toHaveCount(1)
        ->and($occurrences[3]->notes)->toBe([]);
})->with([
    'shift forward' => [GapPolicy::SHIFT_FORWARD, '03:30'],
    'next valid minute' => [GapPolicy::NEXT_VALID_MINUTE, '03:00'],
]);

it('schedules the ambiguous 01:30 exactly once on the fall-back night (America/New_York)', function (OverlapPolicy $policy, string $offset) {
    $occurrences = correctAt('2022-11-04', Frequency::DAILY, 5, '01:30', 'America/New_York', overlap: $policy);

    expect(times($occurrences))->toBe([
        '2022-11-04 01:30 -04:00',
        '2022-11-05 01:30 -04:00',
        "2022-11-06 01:30 {$offset}",
        '2022-11-07 01:30 -05:00',
        '2022-11-08 01:30 -05:00',
    ])
        ->and(array_count_values(dates($occurrences))['2022-11-06'])->toBe(1)
        ->and($occurrences[2]->notes)->toHaveCount(1);
})->with([
    'earlier' => [OverlapPolicy::EARLIER, '-04:00'],
    'later' => [OverlapPolicy::LATER, '-05:00'],
]);

it('keeps 09:00 daily at 09:00 local across both DST changes, while the UTC time moves', function (string $start, array $utcBeforeAfter) {
    $occurrences = correctAt($start, Frequency::DAILY, 10, '09:00', 'Europe/Berlin');

    $localTimes = array_unique(array_map(fn ($o) => $o->local->format('H:i'), $occurrences));
    $utcTimes = array_values(array_unique(array_map(fn ($o) => $o->utc->format('H:i'), $occurrences)));

    expect($localTimes)->toBe(['09:00'])
        ->and($utcTimes)->toBe($utcBeforeAfter);
})->with([
    'spring forward, 2022-03-27' => ['2022-03-22', ['08:00', '07:00']],
    'fall back, 2022-10-30' => ['2022-10-25', ['07:00', '08:00']],
]);

it('keeps a weekly 09:00 reminder at 09:00 local for a whole year', function (string $zone) {
    $occurrences = correctAt('2022-01-03', Frequency::WEEKLY, 52, '09:00', $zone);

    expect(array_unique(array_map(fn ($o) => $o->local->format('H:i'), $occurrences)))->toBe(['09:00'])
        ->and(array_merge(...array_map(fn ($o) => $o->notes, $occurrences)))->toBe([]);
})->with(['Europe/Berlin', 'America/New_York', 'Australia/Sydney', 'Asia/Kolkata', 'Asia/Tokyo']);

it('follows southern-hemisphere DST (Australia/Sydney)', function () {
    $occurrences = correctAt('2022-04-01', Frequency::DAILY, 4, '09:00', 'Australia/Sydney');
    $spring = correctAt('2022-09-30', Frequency::DAILY, 4, '09:00', 'Australia/Sydney');

    expect(array_map(fn ($o) => $o->offset(), $occurrences))->toBe(['+11:00', '+11:00', '+10:00', '+10:00'])
        ->and(array_map(fn ($o) => $o->offset(), $spring))->toBe(['+10:00', '+10:00', '+11:00', '+11:00']);
});

it('uses the half-hour offset of Asia/Kolkata all year', function () {
    $occurrences = correctAt('2022-01-15', Frequency::MONTHLY, 12, '09:00', 'Asia/Kolkata');

    expect(array_unique(array_map(fn ($o) => $o->utc->format('H:i'), $occurrences)))->toBe(['03:30'])
        ->and(array_unique(array_map(fn ($o) => $o->offset(), $occurrences)))->toBe(['+05:30']);
});

it('keeps a constant UTC time in a zone without DST (Asia/Tokyo)', function () {
    $occurrences = correctAt('2022-03-20', Frequency::DAILY, 60, '09:00', 'Asia/Tokyo');

    expect(array_unique(array_map(fn ($o) => $o->utc->format('H:i'), $occurrences)))->toBe(['00:00']);
});

it('resolves the gap only for the monthly occurrence that lands on the DST night', function () {
    $occurrences = correctAt('2022-01-27', Frequency::MONTHLY, 4, '02:30', 'Europe/Berlin');

    expect(times($occurrences))->toBe([
        '2022-01-27 02:30 +01:00',
        '2022-02-27 02:30 +01:00',
        '2022-03-27 03:30 +02:00',
        '2022-04-27 02:30 +02:00',
    ])
        ->and($occurrences[2]->notes)->toHaveCount(1);
});

it('stores wall time and zone, and converts to UTC per occurrence', function () {
    $occurrences = correctAt('2022-03-26', Frequency::DAILY, 2, '09:00', 'Europe/Berlin');

    expect($occurrences[0]->utc->format('Y-m-d H:i e'))->toBe('2022-03-26 08:00 UTC')
        ->and($occurrences[1]->utc->format('Y-m-d H:i e'))->toBe('2022-03-27 07:00 UTC')
        ->and($occurrences[1]->local->getTimezone()->getName())->toBe('Europe/Berlin');
});
