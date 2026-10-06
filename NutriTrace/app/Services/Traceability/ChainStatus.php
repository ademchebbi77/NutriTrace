<?php

namespace App\Services\Traceability;

/**
 * Result of a hash-chain verification.
 */
final readonly class ChainStatus
{
    public function __construct(
        public bool $valid,
        public int $events,
        public ?int $brokenEventId = null,
    ) {}
}
