<?php

namespace App\Enums;

enum TransportType: string
{
    case TRUCK = 'truck';
    case VAN = 'van';
    case TRAIN = 'train';
    case SHIP = 'ship';
    case PLANE = 'plane';

    public function label(): string
    {
        return __('enums.transport_type.'.$this->value);
    }

    /**
     * Font Awesome 5 icon name.
     */
    public function icon(): string
    {
        return match ($this) {
            self::TRUCK => 'fa-truck',
            self::VAN => 'fa-shuttle-van',
            self::TRAIN => 'fa-train',
            self::SHIP => 'fa-ship',
            self::PLANE => 'fa-plane',
        };
    }
}
