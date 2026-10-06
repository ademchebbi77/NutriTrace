<?php

namespace App\Enums;

/**
 * Where an environmental value comes from. Shown next to every figure.
 */
enum DataSource: string
{
    case MEASURED = 'MEASURED';
    case PROVIDED = 'PROVIDED';
    case CALCULATED = 'CALCULATED';

    public function label(): string
    {
        return __('enums.data_source.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::MEASURED => 'success',
            self::PROVIDED => 'info',
            self::CALCULATED => 'secondary',
        };
    }

    /**
     * Sources an actor may choose when declaring a value by hand.
     *
     * @return list<self>
     */
    public static function declarable(): array
    {
        return [self::MEASURED, self::PROVIDED];
    }
}
