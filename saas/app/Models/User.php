<?php

namespace App\Models;

use App\Enums\UserType;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Inside a tenant: staff, owners and clients of one office.
 * On the central connection: platform administrators.
 */
#[Fillable(['type', 'name', 'username', 'email', 'mobile', 'national_id', 'password', 'locale',
    'is_active', 'is_super_admin', 'sms_login_enabled', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /** Mirrors the column defaults so new instances are complete before a refresh. */
    protected $attributes = [
        'type' => 'staff',
        'locale' => 'ar',
        'is_active' => true,
        'is_super_admin' => false,
        'sms_login_enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'sms_login_enabled' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'platform' => ! tenancy()->initialized && $this->is_super_admin,
            'admin' => tenancy()->initialized && $this->type === UserType::Staff,
            'owner' => tenancy()->initialized && $this->type === UserType::Owner && $this->owner()->exists(),
            'client' => tenancy()->initialized && $this->type === UserType::Client && $this->client()->exists(),
            default => false,
        };
    }

    public function owner(): HasOne
    {
        return $this->hasOne(Owner::class);
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
    }
}
