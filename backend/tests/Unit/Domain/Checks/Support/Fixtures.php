<?php

namespace Tests\Unit\Domain\Checks\Support;

use App\Domain\Checks\CheckInput;
use App\Domain\Checks\ReferencePayment;
use App\Domain\Checks\ValueObjects\Address;
use App\Domain\Checks\ValueObjects\Amount;
use App\Domain\Checks\ValueObjects\Network;
use App\Domain\Checks\ValueObjects\ScriptSource;

/**
 * Test-only fixture builders for the Domain\Checks unit suite.
 *
 * checkInput()/referencePayment() default to a fully populated, fully
 * *matching* pairing (every optional CheckInput field set, every value
 * equal to its reference-payment counterpart) — every scenario rule
 * evaluates that pairing as "complete, not triggered". Individual tests
 * override only the field(s) relevant to the scenario under test.
 */
final class Fixtures
{
    public static function checkInput(array $overrides = []): CheckInput
    {
        $defaults = [
            'displayedAddress' => 'addr-baseline',
            'displayedAmount' => '1.00000000',
            'displayedNetwork' => 'BTC',
            'qrAddress' => 'addr-baseline',
            'qrAmount' => '1.00000000',
            'qrNetwork' => 'BTC',
            'copyButtonValue' => 'addr-baseline',
            'addressAfterWatchWindow' => 'addr-baseline',
            'pageScripts' => ['https://payments.example/checkout.js'],
        ];

        return new CheckInput(...array_merge($defaults, $overrides));
    }

    public static function referencePayment(array $overrides = []): ReferencePayment
    {
        $defaults = [
            'id' => '11111111-1111-1111-1111-111111111111',
            'address' => 'addr-baseline',
            'amount' => '1.00000000',
            'network' => 'BTC',
            'allowedScripts' => ['https://payments.example/checkout.js'],
        ];

        $args = array_merge($defaults, $overrides);

        return new ReferencePayment(
            id: $args['id'],
            address: new Address($args['address']),
            amount: new Amount($args['amount']),
            network: new Network($args['network']),
            allowedScripts: array_map(
                static fn (string $script): ScriptSource => new ScriptSource($script),
                $args['allowedScripts'],
            ),
        );
    }

    /**
     * Assert that {$a, $b} (a RuleResult's expected/actual pair, order not
     * guaranteed by contract) is the unordered pair {$expectedA, $expectedB}.
     */
    public static function assertSamePair(?string $a, ?string $b, string $expectedA, string $expectedB): void
    {
        $actual = [$a, $b];
        sort($actual);

        $expected = [$expectedA, $expectedB];
        sort($expected);

        expect($actual)->toBe($expected);
    }
}
