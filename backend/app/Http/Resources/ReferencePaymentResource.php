<?php

namespace App\Http\Resources;

use App\Domain\Checks\ReferencePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an already-built ReferencePayment into the API response
 * contract. Pure mapping only — generation of missing fields belongs to
 * ReferencePaymentGenerator/CreateReferencePayment, not here (see
 * backend-conventions → Mappers).
 *
 * @property-read ReferencePayment $resource
 */
final class ReferencePaymentResource extends JsonResource
{
    /**
     * Contract requires a flat body ({"run_id": ..., ...}), not Laravel's
     * default {"data": {...}} envelope — see backend-conventions → API
     * contract.
     */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'run_id' => $this->resource->id,
            'address' => $this->resource->address->value(),
            'amount' => $this->resource->amount->value(),
            'network' => $this->resource->network->value(),
            'allowed_scripts' => array_map(
                static fn ($script) => $script->value(),
                $this->resource->allowedScripts,
            ),
        ];
    }
}
