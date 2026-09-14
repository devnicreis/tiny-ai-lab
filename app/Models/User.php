<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OrganizationRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'firebase_uid',
        'purchased_credits_balance',
        'has_completed_setup',
        'notify_on_completion',
    ];

    protected $hidden = [
        'password',
        'remember_token',
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
            'password' => 'hashed',
            'purchased_credits_balance' => 'integer',
            'has_completed_setup' => 'boolean',
            'notify_on_completion' => 'boolean',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role', 'joined_at');
    }

    public function roleIn(Organization $organization): ?OrganizationRole
    {
        $role = $organization->roleOf($this);

        return $role !== null ? OrganizationRole::from($role) : null;
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $organization->hasMember($this);
    }
}
