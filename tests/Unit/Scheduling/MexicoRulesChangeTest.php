<?php

use App\Scheduling\Frequency;
use App\Scheduling\MexicoRulesChange;
use App\Scheduling\RecurrenceRule;

function mexicoRule(string $start, Frequency $frequency): RecurrenceRule
{
    return new RecurrenceRule($start, $frequency, 1, '09:00', MexicoRulesChange::ZONE);
}

it('shows reminders pre-computed under the old rules an hour early during the former summer time', function () {
    $naive = (new MexicoRulesChange())->naive(mexicoRule('2022-11-15', Frequency::MONTHLY), 12);

    expect(times($naive))->toBe([
        '2022-11-15 09:00 -06:00',
        '2022-12-15 09:00 -06:00',
        '2023-01-15 09:00 -06:00',
        '2023-02-15 09:00 -06:00',
        '2023-03-15 09:00 -06:00',
        '2023-04-15 08:00 -06:00',
        '2023-05-15 08:00 -06:00',
        '2023-06-15 08:00 -06:00',
        '2023-07-15 08:00 -06:00',
        '2023-08-15 08:00 -06:00',
        '2023-09-15 08:00 -06:00',
        '2023-10-15 08:00 -06:00',
    ]);
});

it('keeps every reminder at 09:00 when expanded from the wall time under the new rules', function () {
    $correct = (new MexicoRulesChange())->correct(mexicoRule('2022-11-15', Frequency::MONTHLY), 12);

    expect(array_unique(array_map(fn ($o) => $o->local->format('H:i P'), $correct)))->toBe(['09:00 -06:00'])
        ->and(array_unique(array_map(fn ($o) => $o->utc->format('H:i'), $correct)))->toBe(['15:00'])
        ->and(count(array_filter($correct, fn ($o) => $o->notes !== [])))->toBe(7)
        ->and($correct[5]->notes)->toBe(['Summer time (UTC-05:00) under the old rules; under the new rules 09:00 is UTC-06:00 (15:00 UTC)'])
        ->and($correct[4]->notes)->toBe([]);
});

it('uses the old summer-time window exactly: first Sunday of April to last Sunday of October 2023', function () {
    $simulation = new MexicoRulesChange();
    $hours = fn (string $start) => array_map(
        fn ($o) => $o->local->format('m-d H:i'),
        $simulation->naive(mexicoRule($start, Frequency::DAILY), 3),
    );

    expect($hours('2023-04-01'))->toBe(['04-01 09:00', '04-02 08:00', '04-03 08:00'])
        ->and($hours('2023-10-28'))->toBe(['10-28 08:00', '10-29 09:00', '10-30 09:00']);
});

it('does not use the tz database for America/Mexico_City', function () {
    $correct = (new MexicoRulesChange())->correct(mexicoRule('2023-07-15', Frequency::DAILY), 1);

    expect($correct[0]->local->getTimezone()->getName())->toBe('-06:00');
});
