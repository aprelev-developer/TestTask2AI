<?php

namespace App\Domain\Checks\ValueObjects;

/**
 * A payment amount as a decimal string — never a float, never normalized
 * (e.g. "1.50" and "1.5" are compared, and treated, as they are observed).
 */
final readonly class Amount
{
    public function __construct(private string $value) {}

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
