<?php

namespace App\Enums;

enum ReportStatus: string
{
    case PENDING = 'PENDING';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case RESOLVED = 'RESOLVED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return __('enums.report_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::UNDER_REVIEW => 'info',
            self::RESOLVED => 'success',
            self::REJECTED => 'secondary',
        };
    }

    /**
     * Open reports still weigh on the transparency score.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::PENDING, self::UNDER_REVIEW], true);
    }
}
