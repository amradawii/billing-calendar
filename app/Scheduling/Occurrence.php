<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;

final class Occurrence
{
    /**
     * @param  int  $index  0 for the start date, 1 for the first renewal, ...
     * @param  CarbonImmutable  $date  calendar date (midnight UTC)
     * @param  list<string>  $notes  human-readable explanation of every adjustment made to this occurrence
     */
    public function __construct(
        public readonly int $index,
        public readonly CarbonImmutable $date,
        public readonly array $notes = [],
    ) {
    }
}
