<?php

namespace App\Domain\Checks;

use App\Domain\Checks\ValueObjects\Address;
use App\Domain\Checks\ValueObjects\Amount;
use App\Domain\Checks\ValueObjects\Network;
use App\Domain\Checks\ValueObjects\ScriptSource;

/**
 * Fills in whichever of address/amount/network/allowedScripts a caller
 * didn't supply with a random, clearly-fictitious value — never something
 * that could pass as a real address on any network. Pure: no DB, no
 * Laravel dependency. Has nothing to do with the comparison logic in
 * CheckRunner/CheckRule — called only from the Application use case that
 * creates a reference payment.
 */
final class ReferencePaymentGenerator
{
    private const NETWORKS = ['BTC', 'ETH', 'TRX'];

    private const DEFAULT_ALLOWED_SCRIPTS = ['https://payments.example/checkout.js'];

    /**
     * @param  string[]|null  $allowedScripts
     */
    public function generate(
        string $id,
        ?string $address = null,
        ?string $amount = null,
        ?string $network = null,
        ?array $allowedScripts = null,
    ): ReferencePayment {
        $networkValue = $network ?? self::NETWORKS[array_rand(self::NETWORKS)];
        $addressValue = $address ?? $this->randomAddressFor($networkValue);
        $amountValue = $amount ?? $this->randomAmount();
        $allowedScriptsValue = $allowedScripts ?? self::DEFAULT_ALLOWED_SCRIPTS;

        return new ReferencePayment(
            id: $id,
            address: new Address($addressValue),
            amount: new Amount($amountValue),
            network: new Network($networkValue),
            allowedScripts: array_map(
                static fn (string $script): ScriptSource => new ScriptSource($script),
                $allowedScriptsValue,
            ),
        );
    }

    private function randomAddressFor(string $network): string
    {
        return match (strtoupper($network)) {
            'ETH' => '0x'.$this->randomHex(40),
            'BTC' => '1'.$this->randomBase58Like(33),
            default => 'test-addr-'.$this->randomHex(24),
        };
    }

    private function randomAmount(): string
    {
        $whole = random_int(0, 1000);
        $fraction = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);

        return "{$whole}.{$fraction}";
    }

    private function randomHex(int $length): string
    {
        $alphabet = '0123456789abcdef';

        return $this->randomString($alphabet, $length);
    }

    private function randomBase58Like(int $length): string
    {
        // Base58 alphabet (no 0, O, I, l) — visually distinguishable from
        // a real Base58Check-encoded address, and unambiguous.
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

        return $this->randomString($alphabet, $length);
    }

    private function randomString(string $alphabet, int $length): string
    {
        $max = strlen($alphabet) - 1;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $max)];
        }

        return $result;
    }
}
