<?php

namespace App\Domain\Checks;

use App\Domain\Checks\Rules\Scenario71Rule;
use App\Domain\Checks\Rules\Scenario72Rule;
use App\Domain\Checks\Rules\Scenario73Rule;
use App\Domain\Checks\Rules\Scenario74Rule;
use App\Domain\Checks\Rules\Scenario75Rule;

/**
 * Runs all 5 CheckRule implementations and applies the §8 priority rules.
 * Pure function: no database access, no side effects. The rule list
 * defaults to the 5 real scenario rules (all zero-dependency, concrete
 * classes) so Laravel can autowire this without any explicit container
 * binding — callers (e.g. tests) may still pass their own CheckRule[] to
 * isolate the aggregation/priority logic from the rules' own behavior.
 */
final class CheckRunner
{
    private const SUSPICION_SCENARIO = '7.5';

    /**
     * @param  CheckRule[]  $rules
     */
    public function __construct(
        private readonly array $rules = [
            new Scenario71Rule,
            new Scenario72Rule,
            new Scenario73Rule,
            new Scenario74Rule,
            new Scenario75Rule,
        ],
    ) {}

    public function run(CheckInput $input, ReferencePayment $reference): CheckResult
    {
        $triggeredScenarios = [];
        $details = [];
        $incompleteChecks = [];
        $tamperingTriggered = false;
        $suspicionTriggered = false;

        foreach ($this->rules as $rule) {
            $result = $rule->evaluate($input, $reference);

            if ($result->incomplete) {
                $incompleteChecks[] = $result->scenario;

                continue;
            }

            if (! $result->triggered) {
                continue;
            }

            $triggeredScenarios[] = $result->scenario;
            $details[] = [
                'scenario' => $result->scenario,
                'expected' => $result->expected,
                'actual' => $result->actual,
            ];

            if ($result->scenario === self::SUSPICION_SCENARIO) {
                $suspicionTriggered = true;
            } else {
                $tamperingTriggered = true;
            }
        }

        $status = match (true) {
            $tamperingTriggered => CheckStatus::TAMPERING_DETECTED,
            $suspicionTriggered => CheckStatus::SUSPICION,
            default => CheckStatus::CLEAN,
        };

        return new CheckResult($status, $triggeredScenarios, $details, $incompleteChecks);
    }
}
