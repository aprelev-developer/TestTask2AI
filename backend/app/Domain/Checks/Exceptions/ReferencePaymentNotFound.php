<?php

namespace App\Domain\Checks\Exceptions;

use RuntimeException;

/**
 * Thrown by ReferencePaymentRepository::findForRun() when `run_id` is
 * syntactically valid but no matching `reference_payments` row exists.
 * This is an application-level 404, never a validation (422) error — the
 * controller (Agent B territory) is responsible for that mapping.
 */
final class ReferencePaymentNotFound extends RuntimeException
{
    public static function forRunId(string $runId): self
    {
        return new self("No reference payment found for run_id [{$runId}].");
    }
}
