<?php

namespace App\Domain\Checks;

/**
 * Internal result status. Mapped to the exact user-facing Russian strings
 * from the spec-compliance skill only at the API boundary (Resource) —
 * never compared against those strings elsewhere.
 */
enum CheckStatus: string
{
    case CLEAN = 'clean';
    case SUSPICION = 'suspicion';
    case TAMPERING_DETECTED = 'tampering_detected';
}
