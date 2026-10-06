<?php

namespace App\Enums;

enum TransportStatus: string
{
    case IN_TRANSIT = 'in_transit';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return __('enums.transport_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::IN_TRANSIT => 'info',
            self::DELIVERED => 'success',
            self::CANCELLED => 'secondary',
        };
    }
}
