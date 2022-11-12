<?php

function schedule(array $overrides = []): string
{
    return '/api/schedule?'.http_build_query(array_merge([
        'start' => '2022-01-31',
        'frequency' => 'monthly',
        'time' => '09:00',
        'zone' => 'UTC',
        'count' => 4,
    ], $overrides));
}

it('returns both lists, the differences and a summary', function () {
    $this->getJson(schedule())
        ->assertOk()
        ->assertJsonPath('rule.start', '2022-01-31')
        ->assertJsonPath('rule.frequency', 'monthly')
        ->assertJsonPath('rule.interval', 1)
        ->assertJsonPath('rule.gap', 'shift_forward')
        ->assertJsonPath('rule.overlap', 'earlier')
        ->assertJsonPath('naive.1.date', '2022-03-03')
        ->assertJsonPath('correct.1.date', '2022-02-28')
        ->assertJsonPath('correct.1.notes.0', 'February 2022 has no 31st: clamped to Feb 28')
        ->assertJsonPath('correct.2.date', '2022-03-31')
        ->assertJsonPath('summary', ['total' => 4, 'differing' => 3])
        ->assertJsonCount(3, 'differences')
        ->assertJsonPath('differences.0.index', 1);
});

it('serialises each occurrence with local time, offset and UTC instant', function () {
    $this->getJson(schedule(['start' => '2022-03-26', 'frequency' => 'daily', 'zone' => 'Europe/Berlin', 'count' => 2]))
        ->assertOk()
        ->assertJsonPath('correct.1', [
            'index' => 1,
            'date' => '2022-03-27',
            'weekday' => 'Sun',
            'time' => '09:00',
            'offset' => '+02:00',
            'utc' => '2022-03-27T07:00Z',
            'notes' => [],
        ]);
});

it('applies the gap and overlap policies', function () {
    $this->getJson(schedule(['start' => '2022-03-27', 'frequency' => 'daily', 'time' => '02:30', 'zone' => 'Europe/Berlin', 'gap' => 'next_valid_minute', 'count' => 1]))
        ->assertJsonPath('correct.0.time', '03:00');

    $this->getJson(schedule(['start' => '2022-11-06', 'frequency' => 'daily', 'time' => '01:30', 'zone' => 'America/New_York', 'overlap' => 'later', 'count' => 1]))
        ->assertJsonPath('correct.0.offset', '-05:00')
        ->assertJsonPath('differences.0.index', 0);
});

it('runs the simulated Mexico rules change without needing a zone', function () {
    $this->getJson(schedule(['start' => '2022-11-15', 'zone' => null, 'scenario' => 'mexico-2022', 'count' => 12]))
        ->assertOk()
        ->assertJsonPath('rule.zone', 'America/Mexico_City')
        ->assertJsonPath('rule.scenario', 'mexico-2022')
        ->assertJsonPath('naive.5.time', '08:00')
        ->assertJsonPath('correct.5.time', '09:00')
        ->assertJsonPath('summary.differing', 7);
});

it('defaults to 12 occurrences', function () {
    $this->getJson(schedule(['count' => null]))->assertOk()->assertJsonCount(12, 'correct');
});

it('accepts the count limits', function (int $count) {
    $this->getJson(schedule(['count' => $count]))->assertOk()->assertJsonCount($count, 'naive');
})->with([1, 60]);

it('rejects counts outside 1-60', function (mixed $count) {
    $this->getJson(schedule(['count' => $count]))->assertUnprocessable()->assertJsonValidationErrors('count');
})->with([0, 61, -1, 'twelve']);

it('rejects an invalid time zone', function (string $zone) {
    $this->getJson(schedule(['zone' => $zone]))->assertUnprocessable()->assertJsonValidationErrors('zone');
})->with(['Mars/Olympus', '+02:00', 'europe/berlin']);

it('rejects an invalid date', function (string $start) {
    $this->getJson(schedule(['start' => $start]))->assertUnprocessable()->assertJsonValidationErrors('start');
})->with(['2022-02-30', '2021-02-29', '31/01/2022', '2022-1-31', 'tomorrow']);

it('rejects invalid values for the other fields', function (string $field, mixed $value) {
    $this->getJson(schedule([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['frequency', 'hourly'],
    ['interval', 0],
    ['interval', 101],
    ['time', '24:00'],
    ['time', '9:00'],
    ['gap', 'skip'],
    ['overlap', 'both'],
    ['scenario', 'brazil-2019'],
]);

it('requires start, frequency, time and zone', function () {
    $this->getJson('/api/schedule')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start', 'frequency', 'time', 'zone']);
});

it('answers validation errors in JSON even without an Accept header', function () {
    $this->get(schedule(['zone' => 'Mars/Olympus']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('zone');
});
