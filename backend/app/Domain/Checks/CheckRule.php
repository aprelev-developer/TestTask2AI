<?php

namespace App\Domain\Checks;

/**
 * One fraud scenario (§7.1 … §7.5). Implementations are pure — given data,
 * they never fetch anything themselves (no Eloquent, no repository calls).
 */
interface CheckRule
{
    /** "7.1" … "7.5" */
    public function scenarioCode(): string;

    public function evaluate(CheckInput $input, ReferencePayment $reference): RuleResult;
}
