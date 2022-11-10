<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;

final class Occurrence
{
    public readonly CarbonImmutable $utc;

    /**
     * @param  int  $index  0 for the start date, 1 for the first renewal, ...
     * @param  CarbonImmutable  $local  when it happens, expressed in the schedule's zone
     * @param  list<string>  $notes  human-readable explanation of every adjustment made to this occurrence
     */
    public function __construct(
        public readonly int $index,
        public readonly CarbonImmutable $local,
        public readonly array $notes = [],
    ) {
        $this->utc = $local->utc();
    }

    /**
     * UTC offset in force at that moment, e.g. "+02:00".
     */
    public function offset(): string
    {
        return $this->local->format('P');
    }
}
