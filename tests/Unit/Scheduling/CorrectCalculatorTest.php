<?php

use App\Scheduling\CorrectCalculator;
use App\Scheduling\Frequency;
use App\Scheduling\RecurrenceRule;

function correct(string $start, Frequency $frequency, int $count, int $interval = 1): array
{
    return (new CorrectCalculator())->occurrences(new RecurrenceRule($start, $frequency, $interval), $count);
}

it('keeps a Jan 31 monthly plan on the last day of short months for 24 months, without drifting', function () {
    $occurrences = correct('2022-01-31', Frequency::MONTHLY, 24);

    expect(dates($occurrences))->toBe([
        '2022-01-31', '2022-02-28', '2022-03-31', '2022-04-30', '2022-05-31', '2022-06-30',
        '2022-07-31', '2022-08-31', '2022-09-30', '2022-10-31', '2022-11-30', '2022-12-31',
        '2023-01-31', '2023-02-28', '2023-03-31', '2023-04-30', '2023-05-31', '2023-06-30',
        '2023-07-31', '2023-08-31', '2023-09-30', '2023-10-31', '2023-11-30', '2023-12-31',
    ]);
});

it('explains every clamped month and only those', function () {
    $occurrences = correct('2022-01-31', Frequency::MONTHLY, 24);

    $clamped = array_values(array_filter($occurrences, fn ($o) => $o->notes !== []));

    expect(dates($clamped))->toBe([
        '2022-02-28', '2022-04-30', '2022-06-30', '2022-09-30', '2022-11-30',
        '2023-02-28', '2023-04-30', '2023-06-30', '2023-09-30', '2023-11-30',
    ])
        ->and($clamped[0]->notes)->toBe(['February 2022 has no 31st: clamped to Feb 28'])
        ->and($clamped[1]->notes)->toBe(['April 2022 has no 31st: clamped to Apr 30']);
});

it('keeps the anchor day for every month-end start', function (string $start, array $expected) {
    expect(dates(correct($start, Frequency::MONTHLY, 4)))->toBe($expected);
})->with([
    'the 28th is a normal day' => ['2022-01-28', ['2022-01-28', '2022-02-28', '2022-03-28', '2022-04-28']],
    'the 29th, common year' => ['2022-01-29', ['2022-01-29', '2022-02-28', '2022-03-29', '2022-04-29']],
    'the 29th, leap year' => ['2024-01-29', ['2024-01-29', '2024-02-29', '2024-03-29', '2024-04-29']],
    'the 30th' => ['2022-01-30', ['2022-01-30', '2022-02-28', '2022-03-30', '2022-04-30']],
    'the 31st, common year' => ['2022-01-31', ['2022-01-31', '2022-02-28', '2022-03-31', '2022-04-30']],
    'the 31st, leap year' => ['2024-01-31', ['2024-01-31', '2024-02-29', '2024-03-31', '2024-04-30']],
    'Feb 28 anchors on the 28th, not on month-end' => ['2022-02-28', ['2022-02-28', '2022-03-28', '2022-04-28', '2022-05-28']],
    'Apr 30 anchors on the 30th, not on month-end' => ['2022-04-30', ['2022-04-30', '2022-05-30', '2022-06-30', '2022-07-30']],
]);

it('names the missing day in the note', function (string $start, string $note) {
    expect(correct($start, Frequency::MONTHLY, 2)[1]->notes)->toBe([$note]);
})->with([
    ['2022-01-29', 'February 2022 has no 29th: clamped to Feb 28'],
    ['2022-01-30', 'February 2022 has no 30th: clamped to Feb 28'],
    ['2024-01-30', 'February 2024 has no 30th: clamped to Feb 29'],
]);

it('renews a Feb 29 yearly plan on Feb 28 in common years and Feb 29 in leap years, across 8 years', function () {
    $occurrences = correct('2020-02-29', Frequency::YEARLY, 9);

    expect(dates($occurrences))->toBe([
        '2020-02-29', '2021-02-28', '2022-02-28', '2023-02-28', '2024-02-29',
        '2025-02-28', '2026-02-28', '2027-02-28', '2028-02-29',
    ])
        ->and($occurrences[1]->notes)->toBe(['2021 is not a leap year: Feb 29 clamped to Feb 28'])
        ->and($occurrences[4]->notes)->toBe([])
        ->and($occurrences[8]->notes)->toBe([]);
});

it('applies the interval to the anchor, not to the previous date', function (string $start, Frequency $frequency, int $interval, array $expected) {
    expect(dates(correct($start, $frequency, count($expected), $interval)))->toBe($expected);
})->with([
    'every 3 months from Nov 30' => ['2022-11-30', Frequency::MONTHLY, 3, ['2022-11-30', '2023-02-28', '2023-05-30', '2023-08-30', '2023-11-30', '2024-02-29']],
    'every 2 months from Dec 31' => ['2022-12-31', Frequency::MONTHLY, 2, ['2022-12-31', '2023-02-28', '2023-04-30', '2023-06-30', '2023-08-31']],
    'every 4 years from Feb 29' => ['2020-02-29', Frequency::YEARLY, 4, ['2020-02-29', '2024-02-29', '2028-02-29']],
    'every 2 days across Feb 29' => ['2024-02-27', Frequency::DAILY, 2, ['2024-02-27', '2024-02-29', '2024-03-02']],
    'every 2 weeks across new year' => ['2022-12-26', Frequency::WEEKLY, 2, ['2022-12-26', '2023-01-09', '2023-01-23']],
]);

it('steps daily and weekly plans by whole calendar days', function (string $start, Frequency $frequency, array $expected) {
    $occurrences = correct($start, $frequency, count($expected));

    expect(dates($occurrences))->toBe($expected)
        ->and(array_merge(...array_map(fn ($o) => $o->notes, $occurrences)))->toBe([]);
})->with([
    'daily across the year end' => ['2022-12-30', Frequency::DAILY, ['2022-12-30', '2022-12-31', '2023-01-01', '2023-01-02']],
    'daily across a DST change date' => ['2022-03-26', Frequency::DAILY, ['2022-03-26', '2022-03-27', '2022-03-28']],
    'weekly' => ['2022-11-07', Frequency::WEEKLY, ['2022-11-07', '2022-11-14', '2022-11-21', '2022-11-28']],
    'weekly from a month-end' => ['2022-01-31', Frequency::WEEKLY, ['2022-01-31', '2022-02-07', '2022-02-14']],
]);

it('numbers occurrences from 0', function () {
    expect(array_map(fn ($o) => $o->index, correct('2022-01-31', Frequency::MONTHLY, 3)))->toBe([0, 1, 2]);
});

it('rejects a count below 1', function () {
    correct('2022-01-31', Frequency::MONTHLY, 0);
})->throws(InvalidArgumentException::class, 'Count must be at least 1');
