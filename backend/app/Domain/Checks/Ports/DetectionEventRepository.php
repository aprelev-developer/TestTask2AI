<?php

namespace App\Domain\Checks\Ports;

use App\Domain\Checks\DetectionEvent;

/**
 * Port for journaling one detection event. Every successfully processed
 * POST /api/checks calls this exactly once; if the write fails, the caller
 * must let the failure propagate (5xx) rather than return a verdict with no
 * journal entry.
 */
interface DetectionEventRepository
{
    public function record(DetectionEvent $event): void;
}
