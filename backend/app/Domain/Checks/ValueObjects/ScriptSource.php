<?php

namespace App\Domain\Checks\ValueObjects;

/**
 * The source of a single <script> tag observed on the page — never
 * normalized (e.g. no trailing-slash stripping, no case-folding).
 */
final readonly class ScriptSource
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
