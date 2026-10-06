<?php

namespace App\Enums;

enum CertificationStatus: string
{
    case PENDING = 'PENDING';
    case VERIFIED = 'VERIFIED';
    case EXPIRED = 'EXPIRED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return __('enums.certification_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::VERIFIED => 'success',
            self::EXPIRED => 'secondary',
            self::REJECTED => 'danger',
        };
    }
}
