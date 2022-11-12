<?php

use App\Scenarios;

beforeEach(fn () => $this->withoutVite());

it('renders the title line and one tab per scenario', function () {
    $response = $this->get('/')->assertOk()
        ->assertSee('Billing calendar')
        ->assertSee('Renewal dates and reminders that survive month-ends, leap years and daylight saving time.');

    foreach (Scenarios::all() as $scenario) {
        $response->assertSee($scenario['label']);
    }
});

it('offers every IANA time zone in the custom form', function () {
    $this->get('/')
        ->assertSee('<option value="Europe/Berlin">', false)
        ->assertSee('<option value="Asia/Kolkata">', false)
        ->assertSee('<option value="Australia/Sydney">', false);
});

it('gives every preset a problem, a solution and valid parameters', function () {
    foreach (Scenarios::all() as $scenario) {
        if ($scenario['id'] !== 'custom') {
            expect($scenario['problem'])->not->toBeEmpty()
                ->and($scenario['solution'])->not->toBeEmpty();
        }

        $this->getJson('/api/schedule?'.http_build_query($scenario['params']))->assertOk();
    }
});
