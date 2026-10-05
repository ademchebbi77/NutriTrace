<?php

namespace App\Services\Scoring;

/**
 * Transparency score with the breakdown shown under "Pourquoi ce score ?".
 */
final readonly class TrustScore
{
    /**
     * @param  list<array{key: string, label: string, points: float, max: float, detail: string}>  $components
     * @param  list<array{key: string, label: string, points: float, count: int}>  $penalties
     */
    public function __construct(
        public int $score,
        public array $components,
        public array $penalties,
    ) {}

    public function color(): string
    {
        return match (true) {
            $this->score >= 75 => 'success',
            $this->score >= 50 => 'warning',
            default => 'danger',
        };
    }

    public function level(): string
    {
        return __('trust.levels.'.match (true) {
            $this->score >= 75 => 'high',
            $this->score >= 50 => 'medium',
            default => 'low',
        });
    }
}
