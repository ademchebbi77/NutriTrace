<?php

namespace App\Enums;

enum EventType: string
{
    case PRODUCTION = 'PRODUCTION';
    case TRANSFORMATION = 'TRANSFORMATION';
    case TRANSPORT = 'TRANSPORT';
    case DISTRIBUTION = 'DISTRIBUTION';
    case SALE = 'SALE';

    public function label(): string
    {
        return __('enums.event_type.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PRODUCTION => 'success',
            self::TRANSFORMATION => 'warning',
            self::TRANSPORT => 'info',
            self::DISTRIBUTION => 'primary',
            self::SALE => 'dark',
        };
    }

    /**
     * Icon name without prefix: valid in Font Awesome 5 (fa-*) for the back office.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PRODUCTION => 'fa-tractor',
            self::TRANSFORMATION => 'fa-industry',
            self::TRANSPORT => 'fa-truck',
            self::DISTRIBUTION => 'fa-store',
            self::SALE => 'fa-cash-register',
        };
    }

    /**
     * Bootstrap Icons name for the public site.
     */
    public function publicIcon(): string
    {
        return match ($this) {
            self::PRODUCTION => 'bi-flower2',
            self::TRANSFORMATION => 'bi-gear-wide-connected',
            self::TRANSPORT => 'bi-truck',
            self::DISTRIBUTION => 'bi-shop',
            self::SALE => 'bi-bag-check',
        };
    }
}
