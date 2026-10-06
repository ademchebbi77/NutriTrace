<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

// role, account_status and is_active are deliberately not fillable: they are only set by
// the registration flow, the approval service, the seeder and the make:admin command.
#[Fillable(['name', 'email', 'password', 'phone', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::CONSOMMATEUR->value,
        'account_status' => AccountStatus::APPROVED->value,
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'account_status' => AccountStatus::class,
            'is_active' => 'boolean',
        ];
    }

    public function organization(): HasOne
    {
        return $this->hasOne(Organization::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reviewed_by');
    }

    /**
     * Products a consumer has bookmarked.
     */
    public function favoriteProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'favorites')->withPivot('created_at');
    }

    /**
     * Lots a consumer has scanned or opened, most recent first.
     */
    public function viewedLots(): BelongsToMany
    {
        return $this->belongsToMany(Lot::class, 'lot_views')->withPivot('viewed_at')->orderByPivot('viewed_at', 'desc');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Approved, active professionals: the actors a lot can be sent to.
     */
    public function scopeOperational(Builder $query): void
    {
        $query->where('is_active', true)->where('account_status', AccountStatus::APPROVED);
    }

    public function scopePendingApproval(Builder $query): void
    {
        $query->where('account_status', AccountStatus::PENDING);
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isPending(): bool
    {
        return $this->account_status === AccountStatus::PENDING;
    }

    /**
     * Approved by an admin (when required) and not switched off.
     */
    public function canAccessBackOffice(): bool
    {
        return $this->is_active && $this->account_status === AccountStatus::APPROVED;
    }

    /**
     * Name shown to other actors and to the public: the organization when there is one.
     */
    public function displayName(): string
    {
        return $this->organization?->name ?? $this->name;
    }

    public function dashboardRoute(): string
    {
        return $this->role->prefix().'.dashboard';
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }
}
