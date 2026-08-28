<?php

namespace App\Domain\Checks;

use App\Domain\Checks\ValueObjects\Address;
use App\Domain\Checks\ValueObjects\Amount;
use App\Domain\Checks\ValueObjects\Network;
use App\Domain\Checks\ValueObjects\ScriptSource;

/**
 * Ground-truth payment data for a check run — the Domain-layer
 * representation of a `reference_payments` row. Deliberately not the
 * Eloquent model: Domain has no Laravel-specific dependency, so
 * Infrastructure maps the Eloquent model into this DTO before handing it
 * to CheckRunner/CheckRule.
 */
final readonly class ReferencePayment
{
    /**
     * @param  ScriptSource[]  $allowedScripts
     */
    public function __construct(
        public string $id,
        public Address $address,
        public Amount $amount,
        public Network $network,
        public array $allowedScripts,
    ) {}
}
