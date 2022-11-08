<?php

use App\Scheduling\Frequency;
use App\Scheduling\RecurrenceRule;

it('keeps the start date as a calendar date at midnight UTC', function () {
    $rule = new RecurrenceRule('2022-01-31', Frequency::MONTHLY, timeZone: 'Europe/Berlin');

    expect($rule->startDate->format('Y-m-d H:i:s e'))->toBe('2022-01-31 00:00:00 UTC');
});

it('defaults to every 1 period at 09:00 UTC', function () {
    $rule = new RecurrenceRule('2022-11-08', Frequency::DAILY);

    expect($rule->interval)->toBe(1)
        ->and($rule->localTime)->toBe('09:00')
        ->and($rule->timeZone)->toBe('UTC');
});

it('rejects invalid start dates', function (string $date) {
    new RecurrenceRule($date, Frequency::MONTHLY);
})->throws(InvalidArgumentException::class, 'Invalid start date')
    ->with(['2022-02-30', '2021-02-29', '2022-13-01', '2022-1-31', '31/01/2022', "2022-01-31\n", '']);

it('rejects an interval below 1', function (int $interval) {
    new RecurrenceRule('2022-01-31', Frequency::MONTHLY, $interval);
})->throws(InvalidArgumentException::class, 'Interval must be at least 1')
    ->with([0, -1]);

it('rejects invalid local times', function (string $time) {
    new RecurrenceRule('2022-01-31', Frequency::DAILY, localTime: $time);
})->throws(InvalidArgumentException::class, 'Invalid local time')
    ->with(['24:00', '9:00', '09:60', '0900', '09:00:00', "09:00\n"]);

it('rejects time zones that are not IANA identifiers', function (string $zone) {
    new RecurrenceRule('2022-01-31', Frequency::DAILY, timeZone: $zone);
})->throws(InvalidArgumentException::class, 'Unknown time zone')
    ->with(['Mars/Olympus', '+02:00', 'europe/berlin', '']);
