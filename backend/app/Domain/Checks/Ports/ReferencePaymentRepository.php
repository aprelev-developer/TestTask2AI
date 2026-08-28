<?php

namespace App\Domain\Checks\Ports;

use App\Domain\Checks\Exceptions\ReferencePaymentNotFound;
use App\Domain\Checks\ReferencePayment;

/**
 * Port for loading ground-truth payment data. Only RunCheck may call this —
 * CheckRunner and every CheckRule are given a ReferencePayment, they never
 * fetch it themselves.
 */
interface ReferencePaymentRepository
{
    /**
     * @throws ReferencePaymentNotFound when `run_id` has no matching row.
     */
    public function findForRun(string $runId): ReferencePayment;
}
