<?php

namespace App\Http\Resources;

use App\Domain\Checks\CheckResult;
use App\Domain\Checks\CheckStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an already-computed CheckResult into the API response contract.
 * Pure mapping only — status/priority/scenario decisions belong to
 * Domain/Application (see backend-conventions → Mappers).
 *
 * @property-read CheckResult $resource
 */
final class CheckResultResource extends JsonResource
{
    /**
     * Contract requires a flat body ({"result": ..., ...}), not Laravel's
     * default {"data": {...}} envelope — see backend-conventions → API
     * contract.
     */
    public static $wrap = null;

    private const STATUS_LABELS = [
        CheckStatus::CLEAN->value => 'Подмена не обнаружена',
        CheckStatus::SUSPICION->value => 'Есть подозрение',
        CheckStatus::TAMPERING_DETECTED->value => 'Обнаружена подмена',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'result' => self::STATUS_LABELS[$this->resource->status->value],
            'triggered_scenarios' => $this->resource->triggeredScenarios,
            'details' => $this->resource->details,
            'incomplete_checks' => $this->resource->incompleteChecks,
        ];
    }
}
