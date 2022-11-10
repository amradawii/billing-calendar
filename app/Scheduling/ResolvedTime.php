<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;

final class ResolvedTime
{
    /**
     * @param  CarbonImmutable  $local  the instant, expressed in the zone
     * @param  string|null  $note  set when the wall time fell in a DST gap or overlap
     */
    public function __construct(
        public readonly CarbonImmutable $local,
        public readonly ?string $note = null,
    ) {
    }
}
