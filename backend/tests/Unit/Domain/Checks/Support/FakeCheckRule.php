<?php

namespace Tests\Unit\Domain\Checks\Support;

use App\Domain\Checks\CheckInput;
use App\Domain\Checks\CheckRule;
use App\Domain\Checks\ReferencePayment;
use App\Domain\Checks\RuleResult;

/**
 * Test double for CheckRule, used only by PriorityTest.php to isolate the
 * §8 priority/aggregation logic of CheckRunner from the concrete behavior of
 * the five real scenario rules (which are covered individually by
 * Scenario71Test.php … Scenario75Test.php).
 */
final readonly class FakeCheckRule implements CheckRule
{
    public function __construct(
        private string $scenario,
        private bool $triggered,
        private bool $incomplete,
        private ?string $expected = null,
        private ?string $actual = null,
    ) {}

    public function scenarioCode(): string
    {
        return $this->scenario;
    }

    public function evaluate(CheckInput $input, ReferencePayment $reference): RuleResult
    {
        return new RuleResult(
            scenario: $this->scenario,
            triggered: $this->triggered,
            incomplete: $this->incomplete,
            expected: $this->expected,
            actual: $this->actual,
        );
    }
}
