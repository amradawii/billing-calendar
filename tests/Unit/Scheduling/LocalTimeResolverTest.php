<?php

use App\Scheduling\GapPolicy;
use App\Scheduling\LocalTimeResolver;
use App\Scheduling\OverlapPolicy;

function resolveWallTime(string $date, string $time, string $zone, GapPolicy $gap = GapPolicy::SHIFT_FORWARD, OverlapPolicy $overlap = OverlapPolicy::EARLIER)
{
    return (new LocalTimeResolver())->resolve($date, $time, new DateTimeZone($zone), $gap, $overlap);
}

it('resolves an ordinary wall time without a note', function () {
    $resolved = resolveWallTime('2022-11-10', '09:00', 'Europe/Berlin');

    expect($resolved->local->format('Y-m-d H:i P'))->toBe('2022-11-10 09:00 +01:00')
        ->and($resolved->local->utc()->format('H:i'))->toBe('08:00')
        ->and($resolved->note)->toBeNull();
});

it('shifts a nonexistent time forward by the length of the gap (Europe/Berlin, 2022-03-27)', function () {
    $resolved = resolveWallTime('2022-03-27', '02:30', 'Europe/Berlin', GapPolicy::SHIFT_FORWARD);

    expect($resolved->local->format('Y-m-d H:i P'))->toBe('2022-03-27 03:30 +02:00')
        ->and($resolved->local->utc()->format('H:i'))->toBe('01:30')
        ->and($resolved->note)->toBe('02:30 does not exist on 2022-03-27 in Europe/Berlin (clocks jump from 02:00 to 03:00): shifted forward 1 hour to 03:30');
});

it('moves a nonexistent time to the first valid minute (Europe/Berlin, 2022-03-27)', function () {
    $resolved = resolveWallTime('2022-03-27', '02:30', 'Europe/Berlin', GapPolicy::NEXT_VALID_MINUTE);

    expect($resolved->local->format('Y-m-d H:i P'))->toBe('2022-03-27 03:00 +02:00')
        ->and($resolved->local->utc()->format('H:i'))->toBe('01:00')
        ->and($resolved->note)->toBe('02:30 does not exist on 2022-03-27 in Europe/Berlin (clocks jump from 02:00 to 03:00): moved to 03:00, the first valid minute');
});

it('treats the exact start of the gap as nonexistent and its end as valid', function (string $time, GapPolicy $policy, string $expected, bool $noted) {
    $resolved = resolveWallTime('2022-03-27', $time, 'Europe/Berlin', $policy);

    expect($resolved->local->format('H:i P'))->toBe($expected)
        ->and($resolved->note !== null)->toBe($noted);
})->with([
    ['01:59', GapPolicy::SHIFT_FORWARD, '01:59 +01:00', false],
    ['02:00', GapPolicy::SHIFT_FORWARD, '03:00 +02:00', true],
    ['02:00', GapPolicy::NEXT_VALID_MINUTE, '03:00 +02:00', true],
    ['02:59', GapPolicy::SHIFT_FORWARD, '03:59 +02:00', true],
    ['02:59', GapPolicy::NEXT_VALID_MINUTE, '03:00 +02:00', true],
    ['03:00', GapPolicy::SHIFT_FORWARD, '03:00 +02:00', false],
]);

it('picks exactly one instant for an ambiguous time (America/New_York, 2022-11-06)', function (OverlapPolicy $policy, string $local, string $utc, string $note) {
    $resolved = resolveWallTime('2022-11-06', '01:30', 'America/New_York', overlap: $policy);

    expect($resolved->local->format('Y-m-d H:i P'))->toBe($local)
        ->and($resolved->local->utc()->format('H:i'))->toBe($utc)
        ->and($resolved->note)->toBe($note);
})->with([
    'earlier' => [OverlapPolicy::EARLIER, '2022-11-06 01:30 -04:00', '05:30',
        '01:30 happens twice on 2022-11-06 in America/New_York (clocks go back from 02:00 to 01:00): using the first one, UTC-04:00', ],
    'later' => [OverlapPolicy::LATER, '2022-11-06 01:30 -05:00', '06:30',
        '01:30 happens twice on 2022-11-06 in America/New_York (clocks go back from 02:00 to 01:00): using the second one, UTC-05:00', ],
]);

it('treats only the repeated hour as ambiguous', function (string $time, string $expected, bool $noted) {
    $resolved = resolveWallTime('2022-11-06', $time, 'America/New_York');

    expect($resolved->local->format('H:i P'))->toBe($expected)
        ->and($resolved->note !== null)->toBe($noted);
})->with([
    ['00:59', '00:59 -04:00', false],
    ['01:00', '01:00 -04:00', true],
    ['01:59', '01:59 -04:00', true],
    ['02:00', '02:00 -05:00', false],
]);

it('handles the southern hemisphere, where summer time starts in October (Australia/Sydney)', function () {
    $gap = resolveWallTime('2022-10-02', '02:30', 'Australia/Sydney');
    $first = resolveWallTime('2022-04-03', '02:30', 'Australia/Sydney', overlap: OverlapPolicy::EARLIER);
    $second = resolveWallTime('2022-04-03', '02:30', 'Australia/Sydney', overlap: OverlapPolicy::LATER);

    expect($gap->local->format('Y-m-d H:i P'))->toBe('2022-10-02 03:30 +11:00')
        ->and($gap->note)->toContain('clocks jump from 02:00 to 03:00')
        ->and($first->local->format('H:i P'))->toBe('02:30 +11:00')
        ->and($second->local->format('H:i P'))->toBe('02:30 +10:00')
        ->and($first->note)->toContain('clocks go back from 03:00 to 02:00');
});

it('resolves zones without DST, including half-hour offsets', function (string $zone, string $date, string $utc) {
    $resolved = resolveWallTime($date, '09:00', $zone);

    expect($resolved->local->format('H:i'))->toBe('09:00')
        ->and($resolved->local->utc()->format('H:i'))->toBe($utc)
        ->and($resolved->note)->toBeNull();
})->with([
    ['Asia/Kolkata', '2022-03-27', '03:30'],
    ['Asia/Kolkata', '2022-11-06', '03:30'],
    ['Asia/Tokyo', '2022-03-27', '00:00'],
    ['Asia/Tokyo', '2022-11-06', '00:00'],
]);

it('describes gaps that are not a whole hour (Australia/Lord_Howe, 30 minutes)', function () {
    $resolved = resolveWallTime('2022-10-02', '02:15', 'Australia/Lord_Howe');

    expect($resolved->local->format('H:i P'))->toBe('02:45 +11:00')
        ->and($resolved->note)->toContain('clocks jump from 02:00 to 02:30): shifted forward 30 minutes to 02:45');
});

it('works with a fixed-offset zone', function () {
    $resolved = resolveWallTime('2023-07-15', '09:00', '-06:00');

    expect($resolved->local->format('H:i P'))->toBe('09:00 -06:00')
        ->and($resolved->note)->toBeNull();
});
