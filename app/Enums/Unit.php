<?php

namespace App\Enums;

enum Unit: string
{
    case KILOGRAM = 'kg';
    case TONNE = 't';
    case LITRE = 'L';
    case PIECE = 'unit';

    public function label(): string
    {
        return __('enums.unit.'.$this->value);
    }

    /**
     * Short symbol shown next to quantities.
     */
    public function symbol(): string
    {
        return $this === self::PIECE ? __('enums.unit_symbol.unit') : $this->value;
    }

    /**
     * Approximate mass of one unit in kilograms, used for transport footprint.
     * Litres are treated as 1 kg (simplification) and pieces have no known mass.
     */
    public function kilograms(): ?float
    {
        return match ($this) {
            self::KILOGRAM, self::LITRE => 1.0,
            self::TONNE => 1000.0,
            self::PIECE => null,
        };
    }
}
