<?php

namespace App\Domain\Checks\ValueObjects;

/**
 * A payment address exactly as observed (page text, QR payload, copy-button
 * value, or a later snapshot) — never normalized, trimmed, or case-folded.
 */
final readonly class Address
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
