<?php

namespace App\Application\Checks;

use App\Domain\Checks\CheckInput;
use App\Domain\Checks\CheckResult;
use App\Domain\Checks\CheckRunner;
use App\Domain\Checks\DetectionEvent;
use App\Domain\Checks\Exceptions\ReferencePaymentNotFound;
use App\Domain\Checks\Ports\DetectionEventRepository;
use App\Domain\Checks\Ports\ReferencePaymentRepository;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Use case: run one antifraud check and journal it.
 *
 * 1. Load the reference payment for $runId (throws ReferencePaymentNotFound
 *    if there is none — the controller maps that to a 404).
 * 2. Run the pure CheckRunner::run() — no transaction, it's a computation.
 * 3. Record exactly one DetectionEvent (the write, and only the write, is
 *    transactional — see EloquentDetectionEventRepository).
 */
final readonly class RunCheck
{
    public function __construct(
        private ReferencePaymentRepository $referencePayments,
        private CheckRunner $runner,
        private DetectionEventRepository $detectionEvents,
    ) {}

    /**
     * @throws ReferencePaymentNotFound
     */
    public function __invoke(string $runId, CheckInput $input): CheckResult
    {
        $reference = $this->referencePayments->findForRun($runId);

        $result = $this->runner->run($input, $reference);

        $requestId = (string) Str::uuid();

        $this->detectionEvents->record(new DetectionEvent(
            id: $requestId,
            runId: $runId,
            requestId: $requestId,
            status: $result->status,
            triggeredScenarios: $result->triggeredScenarios,
            details: $result->details,
            incompleteChecks: $result->incompleteChecks,
            createdAt: new DateTimeImmutable,
        ));

        return $result;
    }
}
