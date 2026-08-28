<?php

namespace App\Domain\Checks\Rules;

use App\Domain\Checks\CheckInput;
use App\Domain\Checks\CheckRule;
use App\Domain\Checks\ReferencePayment;
use App\Domain\Checks\RuleResult;
use App\Domain\Checks\ValueObjects\ScriptSource;

/**
 * §7.5 — a <script> is connected on the page that is not in the reference
 * page's allowed-script list. The only rule that actually reads
 * $reference (the allowed-script list) — 7.1-7.4 ignore it.
 */
final readonly class Scenario75Rule implements CheckRule
{
    public function scenarioCode(): string
    {
        return '7.5';
    }

    public function evaluate(CheckInput $input, ReferencePayment $reference): RuleResult
    {
        if ($input->pageScripts === null) {
            return new RuleResult($this->scenarioCode(), triggered: false, incomplete: true);
        }

        $disallowed = array_values(array_filter(
            $input->pageScripts,
            fn (ScriptSource $script): bool => ! $this->isAllowed($script, $reference->allowedScripts),
        ));

        $triggered = $disallowed !== [];

        return new RuleResult(
            scenario: $this->scenarioCode(),
            triggered: $triggered,
            incomplete: false,
            expected: $triggered ? $this->join($reference->allowedScripts) : null,
            actual: $triggered ? $this->join($disallowed) : null,
        );
    }

    /**
     * @param  ScriptSource[]  $allowedScripts
     */
    private function isAllowed(ScriptSource $script, array $allowedScripts): bool
    {
        foreach ($allowedScripts as $allowed) {
            if ($allowed->equals($script)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  ScriptSource[]  $scripts
     */
    private function join(array $scripts): string
    {
        return implode(', ', array_map(
            static fn (ScriptSource $script): string => $script->value(),
            $scripts,
        ));
    }
}
