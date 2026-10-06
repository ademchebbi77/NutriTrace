<?php

namespace App\Enums;

enum DistributionStatus: string
{
    case PENDING = 'pending';
    case RECEIVED = 'received';
    case IN_STORE = 'in_store';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return __('enums.distribution_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::RECEIVED => 'primary',
            self::IN_STORE => 'success',
            self::REJECTED => 'danger',
        };
    }
}
