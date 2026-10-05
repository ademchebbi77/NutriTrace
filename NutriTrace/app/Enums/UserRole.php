<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case PRODUCTEUR = 'PRODUCTEUR';
    case TRANSFORMATEUR = 'TRANSFORMATEUR';
    case DISTRIBUTEUR = 'DISTRIBUTEUR';
    case CONSOMMATEUR = 'CONSOMMATEUR';

    public function label(): string
    {
        return __('roles.'.$this->value);
    }

    /**
     * URL and route-name prefix of the role's back office area.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::ADMIN => 'admin',
            self::PRODUCTEUR => 'producteur',
            self::TRANSFORMATEUR => 'transformateur',
            self::DISTRIBUTEUR => 'distributeur',
            self::CONSOMMATEUR => 'consommateur',
        };
    }

    /**
     * Professional roles own an organization and need admin approval.
     */
    public function isProfessional(): bool
    {
        return in_array($this, [self::PRODUCTEUR, self::TRANSFORMATEUR, self::DISTRIBUTEUR], true);
    }

    /**
     * Roles that can be chosen on the public registration form.
     *
     * @return list<self>
     */
    public static function registrable(): array
    {
        return [self::CONSOMMATEUR, self::PRODUCTEUR, self::TRANSFORMATEUR, self::DISTRIBUTEUR];
    }
}
