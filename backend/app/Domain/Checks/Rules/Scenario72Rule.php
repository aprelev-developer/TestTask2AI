<?php

namespace App\Domain\Checks\Rules;

use App\Domain\Checks\CheckInput;
use App\Domain\Checks\CheckRule;
use App\Domain\Checks\ReferencePayment;
use App\Domain\Checks\RuleResult;

/**
 * §7.2 — the value prepared for the copy-button click differs from the
 * address displayed on the page. Ignores $reference.
 */
final readonly class Scenario72Rule implements CheckRule
{
    public function scenarioCode(): string
    {
        return '7.2';
    }

    public function evaluate(CheckInput $input, ReferencePayment $reference): RuleResult
    {
        if ($input->copyButtonValue === null) {
            return new RuleResult($this->scenarioCode(), triggered: false, incomplete: true);
        }

        $triggered = ! $input->displayedAddress->equals($input->copyButtonValue);

        return new RuleResult(
            scenario: $this->scenarioCode(),
            triggered: $triggered,
            incomplete: false,
            expected: $triggered ? $input->displayedAddress->value() : null,
            actual: $triggered ? $input->copyButtonValue->value() : null,
        );
    }
}
