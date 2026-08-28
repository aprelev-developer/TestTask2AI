<?php

namespace App\Application\Checks;

use App\Domain\Checks\Ports\ReferencePaymentRepository;
use App\Domain\Checks\ReferencePayment;
use App\Domain\Checks\ReferencePaymentGenerator;
use Illuminate\Support\Str;

/**
 * Use case: create a new test run's ground-truth payment. Tooling for the
 * frontend, not part of the SPEC.md fraud-detection contract — see
 * `backend-conventions` → Test-fixture endpoints.
 */
final readonly class CreateReferencePayment
{
    public function __construct(
        private ReferencePaymentGenerator $generator,
        private ReferencePaymentRepository $repository,
    ) {}

    /**
     * @param  string[]|null  $allowedScripts
     */
    public function __invoke(
        ?string $address,
        ?string $amount,
        ?string $network,
        ?array $allowedScripts,
    ): ReferencePayment {
        $runId = (string) Str::uuid();

        $payment = $this->generator->generate(
            id: $runId,
            address: $address,
            amount: $amount,
            network: $network,
            allowedScripts: $allowedScripts,
        );

        $this->repository->create($payment);

        return $payment;
    }
}
