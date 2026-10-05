<?php

namespace App\Enums;

enum CertificationType: string
{
    case BIO = 'BIO';
    case LOCAL = 'LOCAL';
    case FAIR_TRADE = 'FAIR_TRADE';
    case SUSTAINABLE_AGRICULTURE = 'SUSTAINABLE_AGRICULTURE';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return __('enums.certification_type.'.$this->value);
    }
}
