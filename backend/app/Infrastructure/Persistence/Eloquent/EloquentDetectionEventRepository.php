<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Checks\DetectionEvent;
use App\Domain\Checks\Ports\DetectionEventRepository;
use App\Models\DetectionEvent as DetectionEventModel;
use Illuminate\Support\Facades\DB;

/**
 * The transaction invariant lives here, not in Application: the transaction
 * wraps only this persistence write, never the (pure, already-finished)
 * domain computation in CheckRunner.
 */
final class EloquentDetectionEventRepository implements DetectionEventRepository
{
    public function record(DetectionEvent $event): void
    {
        DB::transaction(static function () use ($event): void {
            DetectionEventModel::query()->create([
                'id' => $event->id,
                'run_id' => $event->runId,
                'request_id' => $event->requestId,
                'result' => $event->status?->value,
                'triggered_scenarios' => $event->triggeredScenarios,
                'details' => $event->details,
                'incomplete_checks' => $event->incompleteChecks,
                'created_at' => $event->createdAt,
            ]);
        });
    }
}
