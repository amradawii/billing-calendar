<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleRequest;
use App\Scheduling\ScheduleComparison;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    public function __invoke(ScheduleRequest $request, ScheduleComparison $comparison): JsonResponse
    {
        $rule = $request->rule();

        $result = $request->isRulesChange()
            ? $comparison->compareRulesChange($rule, $request->occurrenceCount())
            : $comparison->compare($rule, $request->occurrenceCount());

        return response()->json([
            'rule' => [
                'start' => $rule->startDate->format('Y-m-d'),
                'frequency' => $rule->frequency,
                'interval' => $rule->interval,
                'time' => $rule->localTime,
                'zone' => $rule->timeZone,
                'gap' => $rule->gapPolicy,
                'overlap' => $rule->overlapPolicy,
                'count' => $request->occurrenceCount(),
                'scenario' => $request->validated('scenario'),
            ],
            ...$result,
        ]);
    }
}
