<?php

namespace App\Domain\Checks;

/**
 * The outcome of a full check run (all 5 scenarios), already resolved
 * against the §8 priority rules — the API Resource layer only serializes
 * this, it never recomputes status/priority from raw rule results.
 *
 * `status` is null exactly when nothing among 7.1-7.5 triggered but at
 * least one scenario is incomplete — SPEC.md §8 forbids showing "Подмена не
 * обнаружена" in that case, and there is no 4th status, so no positive
 * claim is made at all; the incomplete-check message stands on its own.
 */
final readonly class CheckResult
{
    /**
     * @param  string[]  $triggeredScenarios
     * @param  array<int, array{scenario: string, expected: ?string, actual: ?string}>  $details
     * @param  string[]  $incompleteChecks
     */
    public function __construct(
        public ?CheckStatus $status,
        public array $triggeredScenarios,
        public array $details,
        public array $incompleteChecks,
    ) {}
}
