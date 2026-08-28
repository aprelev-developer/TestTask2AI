<?php

namespace App\Domain\Checks\ValueObjects;

/**
 * A blockchain network identifier as observed — never normalized.
 */
final readonly class Network
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
