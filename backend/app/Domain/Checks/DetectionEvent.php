<?php

namespace App\Domain\Checks;

use DateTimeImmutable;

/**
 * The Domain-layer representation of one `detection_events` journal row —
 * built once per POST /api/checks, in RunCheck, and handed to
 * DetectionEventRepository::record() unchanged.
 */
final readonly class DetectionEvent
{
    /**
     * @param  string[]  $triggeredScenarios
     * @param  array<int, array{scenario: string, expected: ?string, actual: ?string}>  $details
     * @param  string[]  $incompleteChecks
     */
    public function __construct(
        public string $id,
        public string $runId,
        public string $requestId,
        public ?CheckStatus $status,
        public array $triggeredScenarios,
        public array $details,
        public array $incompleteChecks,
        public DateTimeImmutable $createdAt,
    ) {}
}
