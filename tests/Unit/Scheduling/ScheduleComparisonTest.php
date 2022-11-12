<?php

use App\Scheduling\Frequency;
use App\Scheduling\MexicoRulesChange;
use App\Scheduling\OverlapPolicy;
use App\Scheduling\RecurrenceRule;
use App\Scheduling\ScheduleComparison;

function compareRule(RecurrenceRule $rule, int $count): array
{
    return (new ScheduleComparison())->compare($rule, $count);
}

it('flags only the occurrences where naive and correct differ', function () {
    $result = compareRule(new RecurrenceRule('2022-01-31', Frequency::MONTHLY), 12);

    expect($result['summary'])->toBe(['total' => 12, 'differing' => 11])
        ->and(array_column($result['differences'], 'index'))->toBe(range(1, 11));
});

it('reports no differences when nothing can go wrong', function () {
    $result = compareRule(new RecurrenceRule('2022-01-15', Frequency::MONTHLY, 1, '09:00', 'Asia/Tokyo'), 12);

    expect($result['differences'])->toBe([])
        ->and($result['summary']['differing'])->toBe(0);
});

it('explains a date difference', function () {
    $result = compareRule(new RecurrenceRule('2020-02-29', Frequency::YEARLY), 3);

    expect($result['differences'][0]['reason'])->toBe(
        'Different date: the naive code adds 1 year to the previous renewal instead of to the start date, '
        .'so an overflow past the end of a short month carries into every later renewal.'
    );
});

it('explains a time difference caused by keeping the UTC time', function () {
    $result = compareRule(new RecurrenceRule('2022-10-27', Frequency::DAILY, 1, '09:00', 'Europe/Berlin'), 8);

    expect(array_column($result['differences'], 'index'))->toBe([3, 4, 5, 6, 7])
        ->and($result['differences'][0]['reason'])->toBe(
            'Different time: the naive code keeps the UTC time of the first reminder (07:00 UTC), '
            .'so once the offset is UTC+01:00 it fires at 08:00 local instead of 09:00.'
        );
});

it('explains both a date and a time difference on the same row', function () {
    $result = compareRule(new RecurrenceRule('2022-01-31', Frequency::MONTHLY, 1, '09:00', 'Europe/Berlin'), 3);

    expect($result['differences'][1]['reason'])
        ->toStartWith('Different date:')
        ->toContain('Different time:');
});

it('explains an overlap where the wall time is the same but the instant is not', function () {
    $rule = new RecurrenceRule('2022-11-06', Frequency::DAILY, 1, '01:30', 'America/New_York', overlapPolicy: OverlapPolicy::LATER);

    expect(compareRule($rule, 1)['differences'][0]['reason'])->toBe(
        'Same wall time, different instant: the naive code fires at the UTC-04:00 01:30, the overlap policy chose the UTC-05:00 one.'
    );
});

it('does not flag the DST gap night itself when both land on 03:30, but the correct side explains it', function () {
    $result = compareRule(new RecurrenceRule('2022-03-26', Frequency::DAILY, 1, '02:30', 'Europe/Berlin'), 3);

    expect(array_column($result['differences'], 'index'))->toBe([2])
        ->and($result['correct'][1]->notes)->toHaveCount(1);
});

it('explains the rules change', function () {
    $result = (new ScheduleComparison())->compareRulesChange(
        new RecurrenceRule('2022-11-15', Frequency::MONTHLY, 1, '09:00', MexicoRulesChange::ZONE), 12,
    );

    expect($result['summary'])->toBe(['total' => 12, 'differing' => 7])
        ->and($result['differences'][0]['reason'])->toBe(
            'Different time: the naive code stored a UTC instant computed with the old rules (14:00 UTC), '
            .'which is 08:00 under the new rules instead of 09:00.'
        );
});
