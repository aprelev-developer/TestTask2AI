<?php

namespace App\Domain\Checks\Rules;

use App\Domain\Checks\CheckInput;
use App\Domain\Checks\CheckRule;
use App\Domain\Checks\ReferencePayment;
use App\Domain\Checks\RuleResult;

/**
 * §7.3 — the address on the page changes within 5 seconds of starting the
 * check. `displayedAddress` is the value fixed as the reference point when
 * the check started; `addressAfterWatchWindow` is what was observed at the
 * end of the 5s window. Ignores $reference.
 */
final readonly class Scenario73Rule implements CheckRule
{
    public function scenarioCode(): string
    {
        return '7.3';
    }

    public function evaluate(CheckInput $input, ReferencePayment $reference): RuleResult
    {
        if ($input->addressAfterWatchWindow === null) {
            return new RuleResult($this->scenarioCode(), triggered: false, incomplete: true);
        }

        $triggered = ! $input->displayedAddress->equals($input->addressAfterWatchWindow);

        return new RuleResult(
            scenario: $this->scenarioCode(),
            triggered: $triggered,
            incomplete: false,
            expected: $triggered ? $input->displayedAddress->value() : null,
            actual: $triggered ? $input->addressAfterWatchWindow->value() : null,
        );
    }
}
