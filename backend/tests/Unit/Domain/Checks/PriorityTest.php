<?php

/**
 * SPEC.md §8 / §9 — priority rules for aggregating the five scenario
 * results into one CheckResult (CheckRunner). Uses FakeCheckRule test
 * doubles instead of the real Scenario7xRule classes so this file tests
 * exactly the aggregation/priority logic, independent of any single
 * scenario's own comparison behavior (covered by Scenario71Test.php …
 * Scenario75Test.php). CheckRunner accepts an injectable CheckRule[] for
 * exactly this purpose.
 *
 * §8 rules under test:
 * 1. Substitution (7.1-7.4) suppresses the SUSPICION *status*, but does not
 *    hide 7.5 from triggered_scenarios and does not suppress the
 *    incomplete-checks technical message.
 * 2. SUSPICION is only reported when no substitution was found among the
 *    completed scenarios.
 * 3. An incomplete check must never allow "Подмена не обнаружена" to stand
 *    unaccompanied — incompleteChecks must be non-empty whenever a scenario
 *    could not be evaluated, even if the aggregate status resolves to CLEAN.
 * 4. An incomplete check accompanies a found result (TAMPERING_DETECTED or
 *    SUSPICION) rather than replacing it.
 */

use App\Domain\Checks\CheckRunner;
use App\Domain\Checks\CheckStatus;
use Tests\Unit\Domain\Checks\Support\FakeCheckRule;
use Tests\Unit\Domain\Checks\Support\Fixtures;

it('lets a tampering finding (7.1-7.4) suppress the suspicion status while still reporting 7.5 as triggered', function () {
    $runner = new CheckRunner([
        new FakeCheckRule('7.1', triggered: true, incomplete: false, expected: 'a', actual: 'b'),
        new FakeCheckRule('7.2', triggered: false, incomplete: false),
        new FakeCheckRule('7.3', triggered: false, incomplete: false),
        new FakeCheckRule('7.4', triggered: false, incomplete: false),
        new FakeCheckRule('7.5', triggered: true, incomplete: false, actual: 'evil.js'),
    ]);

    $result = $runner->run(Fixtures::checkInput(), Fixtures::referencePayment());

    expect($result->status)->toBe(CheckStatus::TAMPERING_DETECTED)
        ->and($result->triggeredScenarios)->toContain('7.1')
        ->and($result->triggeredScenarios)->toContain('7.5')
        ->and($result->incompleteChecks)->toBe([]);
});

it('reports suspicion only when no substitution (7.1-7.4) was found among completed scenarios', function () {
    $runner = new CheckRunner([
        new FakeCheckRule('7.1', triggered: false, incomplete: false),
        new FakeCheckRule('7.2', triggered: false, incomplete: false),
        new FakeCheckRule('7.3', triggered: false, incomplete: false),
        new FakeCheckRule('7.4', triggered: false, incomplete: false),
        new FakeCheckRule('7.5', triggered: true, incomplete: false, actual: 'evil.js'),
    ]);

    $result = $runner->run(Fixtures::checkInput(), Fixtures::referencePayment());

    expect($result->status)->toBe(CheckStatus::SUSPICION)
        ->and($result->triggeredScenarios)->toBe(['7.5']);
});

it('resolves to clean when nothing triggered and every scenario completed', function () {
    $runner = new CheckRunner([
        new FakeCheckRule('7.1', triggered: false, incomplete: false),
        new FakeCheckRule('7.2', triggered: false, incomplete: false),
        new FakeCheckRule('7.3', triggered: false, incomplete: false),
        new FakeCheckRule('7.4', triggered: false, incomplete: false),
        new FakeCheckRule('7.5', triggered: false, incomplete: false),
    ]);

    $result = $runner->run(Fixtures::checkInput(), Fixtures::referencePayment());

    expect($result->status)->toBe(CheckStatus::CLEAN)
        ->and($result->triggeredScenarios)->toBe([])
        ->and($result->incompleteChecks)->toBe([]);
});

it('does not let an incomplete check stand unflagged when nothing else triggered (no bare "clean" without an incomplete signal)', function () {
    $runner = new CheckRunner([
        new FakeCheckRule('7.1', triggered: false, incomplete: false),
        new FakeCheckRule('7.2', triggered: false, incomplete: true),
        new FakeCheckRule('7.3', triggered: false, incomplete: false),
        new FakeCheckRule('7.4', triggered: false, incomplete: false),
        new FakeCheckRule('7.5', triggered: false, incomplete: false),
    ]);

    $result = $runner->run(Fixtures::checkInput(), Fixtures::referencePayment());

    // The status enum has no 4th "incomplete" case (per the canonical Domain
    // contract), so CLEAN is the only value it can resolve to here — but the
    // SPEC forbids showing "Подмена не обнаружена" bare in this situation.
    // That invariant is only satisfiable if incompleteChecks is populated,
    // so any consumer of CheckResult MUST branch on incompleteChecks before
    // ever presenting the CLEAN status to a user.
    expect($result->status)->toBe(CheckStatus::CLEAN)
        ->and($result->incompleteChecks)->toBe(['7.2']);
});

it('accompanies a tampering result with the incomplete-checks message rather than replacing it', function () {
    $runner = new CheckRunner([
        new FakeCheckRule('7.1', triggered: true, incomplete: false, expected: 'a', actual: 'b'),
        new FakeCheckRule('7.2', triggered: false, incomplete: false),
        new FakeCheckRule('7.3', triggered: false, incomplete: false),
        new FakeCheckRule('7.4', triggered: false, incomplete: false),
        new FakeCheckRule('7.5', triggered: false, incomplete: true),
    ]);

    $result = $runner->run(Fixtures::checkInput(), Fixtures::referencePayment());

    expect($result->status)->toBe(CheckStatus::TAMPERING_DETECTED)
        ->and($result->triggeredScenarios)->toBe(['7.1'])
        ->and($result->incompleteChecks)->toBe(['7.5']);
});

it('accompanies a suspicion result with the incomplete-checks message rather than dropping it', function () {
    $runner = new CheckRunner([
        new FakeCheckRule('7.1', triggered: false, incomplete: false),
        new FakeCheckRule('7.2', triggered: false, incomplete: false),
        new FakeCheckRule('7.3', triggered: false, incomplete: true),
        new FakeCheckRule('7.4', triggered: false, incomplete: false),
        new FakeCheckRule('7.5', triggered: true, incomplete: false, actual: 'evil.js'),
    ]);

    $result = $runner->run(Fixtures::checkInput(), Fixtures::referencePayment());

    expect($result->status)->toBe(CheckStatus::SUSPICION)
        ->and($result->triggeredScenarios)->toBe(['7.5'])
        ->and($result->incompleteChecks)->toBe(['7.3']);
});

it('lists every incomplete scenario, not just the first one found', function () {
    $runner = new CheckRunner([
        new FakeCheckRule('7.1', triggered: false, incomplete: true),
        new FakeCheckRule('7.2', triggered: false, incomplete: false),
        new FakeCheckRule('7.3', triggered: false, incomplete: false),
        new FakeCheckRule('7.4', triggered: false, incomplete: true),
        new FakeCheckRule('7.5', triggered: false, incomplete: false),
    ]);

    $result = $runner->run(Fixtures::checkInput(), Fixtures::referencePayment());

    expect($result->incompleteChecks)->toContain('7.1')
        ->and($result->incompleteChecks)->toContain('7.4')
        ->and($result->incompleteChecks)->toHaveCount(2);
});

it('lists every triggered scenario when more than one of 7.1-7.4 fires', function () {
    $runner = new CheckRunner([
        new FakeCheckRule('7.1', triggered: true, incomplete: false, expected: 'a', actual: 'b'),
        new FakeCheckRule('7.2', triggered: false, incomplete: false),
        new FakeCheckRule('7.3', triggered: false, incomplete: false),
        new FakeCheckRule('7.4', triggered: true, incomplete: false, expected: 'c', actual: 'd'),
        new FakeCheckRule('7.5', triggered: false, incomplete: false),
    ]);

    $result = $runner->run(Fixtures::checkInput(), Fixtures::referencePayment());

    expect($result->status)->toBe(CheckStatus::TAMPERING_DETECTED)
        ->and($result->triggeredScenarios)->toContain('7.1')
        ->and($result->triggeredScenarios)->toContain('7.4')
        ->and($result->triggeredScenarios)->toHaveCount(2);
});
