<?php

use App\Scheduling\CorrectCalculator;
use App\Scheduling\Frequency;
use App\Scheduling\NaiveCalculator;
use App\Scheduling\RecurrenceRule;

function naive(string $start, Frequency $frequency, int $count, int $interval = 1): array
{
    return (new NaiveCalculator())->occurrences(new RecurrenceRule($start, $frequency, $interval), $count);
}

it('overflows Jan 31 into March and stays on the 3rd', function () {
    expect(dates(naive('2022-01-31', Frequency::MONTHLY, 5)))
        ->toBe(['2022-01-31', '2022-03-03', '2022-04-03', '2022-05-03', '2022-06-03']);
});

it('overflows Feb 29 to Mar 1 and never returns to Feb 29', function () {
    expect(dates(naive('2020-02-29', Frequency::YEARLY, 5)))
        ->toBe(['2020-02-29', '2021-03-01', '2022-03-01', '2023-03-01', '2024-03-01']);
});

it('chains the interval from the previous date', function () {
    expect(dates(naive('2022-11-30', Frequency::MONTHLY, 4, 3)))
        ->toBe(['2022-11-30', '2023-03-02', '2023-06-02', '2023-09-02']);
});

it('never adds notes', function () {
    expect(array_merge(...array_map(fn ($o) => $o->notes, naive('2022-01-31', Frequency::MONTHLY, 12))))->toBe([]);
});

it('agrees with the correct calculator when no month-end or leap day is involved', function (string $start, Frequency $frequency) {
    $rule = new RecurrenceRule($start, $frequency);

    expect(dates((new NaiveCalculator())->occurrences($rule, 12)))
        ->toBe(dates((new CorrectCalculator())->occurrences($rule, 12)));
})->with([
    ['2022-01-15', Frequency::MONTHLY],
    ['2022-01-28', Frequency::MONTHLY],
    ['2022-11-07', Frequency::WEEKLY],
    ['2022-12-30', Frequency::DAILY],
    ['2022-03-01', Frequency::YEARLY],
]);
