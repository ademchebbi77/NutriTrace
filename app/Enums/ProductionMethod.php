<?php

namespace App\Enums;

enum ProductionMethod: string
{
    case CONVENTIONAL = 'conventional';
    case ORGANIC = 'organic';
    case INTEGRATED = 'integrated';
    case PERMACULTURE = 'permaculture';
    case OTHER = 'other';

    public function label(): string
    {
        return __('enums.production_method.'.$this->value);
    }
}
