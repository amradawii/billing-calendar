<?php

namespace App\Scheduling;

/**
 * Runs the naive and the correct calculation for the same rule and explains every occurrence where they differ.
 */
final class ScheduleComparison
{
    public function __construct(
        private readonly NaiveCalculator $naive = new NaiveCalculator(),
        private readonly CorrectCalculator $correct = new CorrectCalculator(),
        private readonly MexicoRulesChange $rulesChange = new MexicoRulesChange(),
    ) {
    }

    /**
     * @return array{naive: list<Occurrence>, correct: list<Occurrence>, differences: list<array{index: int, reason: string}>, summary: array{total: int, differing: int}}
     */
    public function compare(RecurrenceRule $rule, int $count): array
    {
        return $this->result($rule, $this->naive->occurrences($rule, $count), $this->correct->occurrences($rule, $count), false);
    }

    /**
     * The simulated Mexico 2022 rules change: pre-computed under the old rules vs expanded under the new ones.
     *
     * @return array{naive: list<Occurrence>, correct: list<Occurrence>, differences: list<array{index: int, reason: string}>, summary: array{total: int, differing: int}}
     */
    public function compareRulesChange(RecurrenceRule $rule, int $count): array
    {
        return $this->result($rule, $this->rulesChange->naive($rule, $count), $this->rulesChange->correct($rule, $count), true);
    }

    /**
     * @param  list<Occurrence>  $naive
     * @param  list<Occurrence>  $correct
     */
    private function result(RecurrenceRule $rule, array $naive, array $correct, bool $rulesChange): array
    {
        $differences = [];
        foreach ($correct as $i => $occurrence) {
            if (! $naive[$i]->utc->equalTo($occurrence->utc)) {
                $differences[] = ['index' => $i, 'reason' => $this->reason($rule, $naive[0], $naive[$i], $occurrence, $rulesChange)];
            }
        }

        return [
            'naive' => $naive,
            'correct' => $correct,
            'differences' => $differences,
            'summary' => ['total' => count($correct), 'differing' => count($differences)],
        ];
    }

    private function reason(RecurrenceRule $rule, Occurrence $firstNaive, Occurrence $naive, Occurrence $correct, bool $rulesChange): string
    {
        $reasons = [];

        $monthlyOrYearly = in_array($rule->frequency, [Frequency::MONTHLY, Frequency::YEARLY], true);
        if ($monthlyOrYearly && $naive->local->format('Y-m-d') !== $correct->local->format('Y-m-d')) {
            $step = $rule->interval.' '.($rule->frequency === Frequency::MONTHLY ? 'month' : 'year').($rule->interval > 1 ? 's' : '');
            $reasons[] = "Different date: the naive code adds {$step} to the previous renewal instead of to the start date, "
                .'so an overflow past the end of a short month carries into every later renewal.';
        }

        if ($naive->local->format('H:i') !== $correct->local->format('H:i')) {
            $reasons[] = $rulesChange
                ? "Different time: the naive code stored a UTC instant computed with the old rules ({$naive->utc->format('H:i')} UTC), which is {$naive->local->format('H:i')} under the new rules instead of {$correct->local->format('H:i')}."
                : "Different time: the naive code keeps the UTC time of the first reminder ({$firstNaive->utc->format('H:i')} UTC), so once the offset is UTC{$naive->offset()} it fires at {$naive->local->format('H:i')} local instead of {$correct->local->format('H:i')}.";
        } elseif ($reasons === []) {
            $reasons[] = "Same wall time, different instant: the naive code fires at the UTC{$naive->offset()} {$naive->local->format('H:i')}, the overlap policy chose the UTC{$correct->offset()} one.";
        }

        return implode(' ', $reasons);
    }
}
