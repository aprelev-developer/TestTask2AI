<?php

namespace App\Domain\Checks\Rules;

use App\Domain\Checks\CheckInput;
use App\Domain\Checks\CheckRule;
use App\Domain\Checks\ReferencePayment;
use App\Domain\Checks\RuleResult;

/**
 * §7.4 — the amount and/or network encoded in the QR code differ from the
 * amount/network shown on the page. Both qrAmount and qrNetwork must be
 * present to evaluate this scenario — a single scenario, not two, so it's
 * incomplete unless both were observed. Ignores $reference.
 */
final readonly class Scenario74Rule implements CheckRule
{
    public function scenarioCode(): string
    {
        return '7.4';
    }

    public function evaluate(CheckInput $input, ReferencePayment $reference): RuleResult
    {
        if ($input->qrAmount === null || $input->qrNetwork === null) {
            return new RuleResult($this->scenarioCode(), triggered: false, incomplete: true);
        }

        $amountMismatch = ! $input->displayedAmount->equals($input->qrAmount);
        $networkMismatch = ! $input->displayedNetwork->equals($input->qrNetwork);
        $triggered = $amountMismatch || $networkMismatch;

        return new RuleResult(
            scenario: $this->scenarioCode(),
            triggered: $triggered,
            incomplete: false,
            expected: $triggered ? sprintf(
                'amount=%s; network=%s',
                $input->displayedAmount->value(),
                $input->displayedNetwork->value(),
            ) : null,
            actual: $triggered ? sprintf(
                'amount=%s; network=%s',
                $input->qrAmount->value(),
                $input->qrNetwork->value(),
            ) : null,
        );
    }
}
