<?php

namespace App\Domain\Checks\Rules;

use App\Domain\Checks\CheckInput;
use App\Domain\Checks\CheckRule;
use App\Domain\Checks\ReferencePayment;
use App\Domain\Checks\RuleResult;

/**
 * §7.1 — the address shown on the page differs from the address encoded in
 * the QR code. Ignores $reference: this scenario compares two observations
 * already present on CheckInput.
 */
final readonly class Scenario71Rule implements CheckRule
{
    public function scenarioCode(): string
    {
        return '7.1';
    }

    public function evaluate(CheckInput $input, ReferencePayment $reference): RuleResult
    {
        if ($input->qrAddress === null) {
            return new RuleResult($this->scenarioCode(), triggered: false, incomplete: true);
        }

        $triggered = ! $input->displayedAddress->equals($input->qrAddress);

        return new RuleResult(
            scenario: $this->scenarioCode(),
            triggered: $triggered,
            incomplete: false,
            expected: $triggered ? $input->displayedAddress->value() : null,
            actual: $triggered ? $input->qrAddress->value() : null,
        );
    }
}
