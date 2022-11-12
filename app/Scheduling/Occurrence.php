<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;
use JsonSerializable;

final class Occurrence implements JsonSerializable
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

    /**
     * @return array{index: int, date: string, weekday: string, time: string, offset: string, utc: string, notes: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'index' => $this->index,
            'date' => $this->local->format('Y-m-d'),
            'weekday' => $this->local->format('D'),
            'time' => $this->local->format('H:i'),
            'offset' => $this->offset(),
            'utc' => $this->utc->format('Y-m-d\TH:i\Z'),
            'notes' => $this->notes,
        ];
    }
}
