<?php

namespace App\Http\Requests;

use App\Scheduling\Frequency;
use App\Scheduling\GapPolicy;
use App\Scheduling\MexicoRulesChange;
use App\Scheduling\OverlapPolicy;
use App\Scheduling\RecurrenceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ScheduleRequest extends FormRequest
{
    public const MAX_COUNT = 60;

    public const RULES_CHANGE_SCENARIO = 'mexico-2022';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start' => ['required', 'date_format:Y-m-d'],
            'frequency' => ['required', new Enum(Frequency::class)],
            'interval' => ['sometimes', 'integer', 'between:1,100'],
            'time' => ['required', 'date_format:H:i'],
            'zone' => ['exclude_if:scenario,'.self::RULES_CHANGE_SCENARIO, 'required', 'timezone'],
            'gap' => ['sometimes', new Enum(GapPolicy::class)],
            'overlap' => ['sometimes', new Enum(OverlapPolicy::class)],
            'count' => ['sometimes', 'integer', 'between:1,'.self::MAX_COUNT],
            'scenario' => ['sometimes', 'in:'.self::RULES_CHANGE_SCENARIO],
        ];
    }

    public function isRulesChange(): bool
    {
        return $this->validated('scenario') === self::RULES_CHANGE_SCENARIO;
    }

    public function occurrenceCount(): int
    {
        return (int) $this->validated('count', 12);
    }

    public function rule(): RecurrenceRule
    {
        return new RecurrenceRule(
            $this->validated('start'),
            Frequency::from($this->validated('frequency')),
            (int) $this->validated('interval', 1),
            $this->validated('time'),
            $this->isRulesChange() ? MexicoRulesChange::ZONE : $this->validated('zone'),
            GapPolicy::from($this->validated('gap', GapPolicy::SHIFT_FORWARD->value)),
            OverlapPolicy::from($this->validated('overlap', OverlapPolicy::EARLIER->value)),
        );
    }
}
