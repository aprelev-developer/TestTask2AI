<?php

namespace App\Domain\Checks;

/**
 * The outcome of a single CheckRule evaluation.
 *
 * `incomplete` means the check could not be technically evaluated (e.g. the
 * relevant observation was null) — it is never true at the same time as
 * `triggered`. When `triggered` is true, `expected`/`actual` carry the raw
 * compared values (still value-object strings, no presentation formatting).
 */
final readonly class RuleResult
{
    public function __construct(
        public string $scenario,
        public bool $triggered,
        public bool $incomplete,
        public ?string $expected = null,
        public ?string $actual = null,
    ) {}
}
