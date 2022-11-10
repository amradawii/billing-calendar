<?php

use App\Scheduling\Occurrence;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the Laravel application. Unit tests in
| tests/Unit are plain PHP and do not boot the framework.
|
*/

uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * @param  list<Occurrence>  $occurrences
 * @return list<string>
 */
function dates(array $occurrences): array
{
    return array_map(fn (Occurrence $o) => $o->local->format('Y-m-d'), $occurrences);
}

/**
 * @param  list<Occurrence>  $occurrences
 * @return list<string> local date, time and offset, e.g. "2022-03-27 03:30 +02:00"
 */
function times(array $occurrences): array
{
    return array_map(fn (Occurrence $o) => $o->local->format('Y-m-d H:i P'), $occurrences);
}
