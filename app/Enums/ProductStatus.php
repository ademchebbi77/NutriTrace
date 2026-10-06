<?php

namespace App\Enums;

enum ProductStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return __('enums.product_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PUBLISHED => 'success',
            self::ARCHIVED => 'dark',
        };
    }
}
