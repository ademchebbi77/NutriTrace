<?php

namespace App\Enums;

enum LotStatus: string
{
    case CREATED = 'created';
    case IN_TRANSFORMATION = 'in_transformation';
    case IN_TRANSIT = 'in_transit';
    case DISTRIBUTED = 'distributed';
    case IN_STORE = 'in_store';
    case SOLD_OUT = 'sold_out';

    public function label(): string
    {
        return __('enums.lot_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'secondary',
            self::IN_TRANSFORMATION => 'warning',
            self::IN_TRANSIT => 'info',
            self::DISTRIBUTED => 'primary',
            self::IN_STORE => 'success',
            self::SOLD_OUT => 'dark',
        };
    }
}
