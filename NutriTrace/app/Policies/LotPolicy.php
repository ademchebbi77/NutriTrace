<?php

namespace App\Policies;

use App\Enums\LotStatus;
use App\Enums\UserRole;
use App\Models\Lot;
use App\Models\User;

class LotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR, UserRole::DISTRIBUTEUR);
    }

    /**
     * Admins and every actor that produced, held, sent, received or transformed the lot.
     */
    public function view(User $user, Lot $lot): bool
    {
        return $user->isAdmin() || $lot->isHeldBy($user) || $lot->isVisibleTo($user);
    }

    /**
     * Only the actor currently holding the lot can act on it.
     */
    public function update(User $user, Lot $lot): bool
    {
        return $lot->isHeldBy($user) && $lot->status !== LotStatus::SOLD_OUT;
    }

    /**
     * Producers and transformers can send a lot they hold while it is at rest.
     */
    public function send(User $user, Lot $lot): bool
    {
        return $lot->isHeldBy($user)
            && $lot->isAvailable()
            && $user->hasRole(UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR);
    }

    /**
     * The holder declares the environmental data of its lot.
     */
    public function declareImpact(User $user, Lot $lot): bool
    {
        return $lot->isHeldBy($user) && $user->hasRole(UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR);
    }

    /**
     * Certifications can be attached by the holder or by the actor who made the lot.
     */
    public function certify(User $user, Lot $lot): bool
    {
        return $user->hasRole(UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR)
            && ($lot->isHeldBy($user)
                || $lot->production?->producer_id === $user->id
                || $lot->transformation?->transformer_id === $user->id);
    }
}
