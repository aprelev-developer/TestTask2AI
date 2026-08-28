<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Checks\Exceptions\ReferencePaymentNotFound;
use App\Domain\Checks\Ports\ReferencePaymentRepository;
use App\Domain\Checks\ReferencePayment as DomainReferencePayment;
use App\Domain\Checks\ValueObjects\Address;
use App\Domain\Checks\ValueObjects\Amount;
use App\Domain\Checks\ValueObjects\Network;
use App\Domain\Checks\ValueObjects\ScriptSource;
use App\Models\ReferencePayment as ReferencePaymentModel;

final class EloquentReferencePaymentRepository implements ReferencePaymentRepository
{
    public function findForRun(string $runId): DomainReferencePayment
    {
        $model = ReferencePaymentModel::query()->find($runId);

        if (! $model instanceof ReferencePaymentModel) {
            throw ReferencePaymentNotFound::forRunId($runId);
        }

        /** @var string[] $allowedScripts */
        $allowedScripts = $model->allowed_scripts;

        return new DomainReferencePayment(
            id: (string) $model->id,
            address: new Address((string) $model->address),
            amount: new Amount((string) $model->amount),
            network: new Network((string) $model->network),
            allowedScripts: array_map(
                static fn (string $script): ScriptSource => new ScriptSource($script),
                $allowedScripts,
            ),
        );
    }
}
