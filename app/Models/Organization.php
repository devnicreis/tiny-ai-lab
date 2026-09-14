<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'plan_credits_balance',
        'plan_credits_used_this_month',
        'purchased_credits_balance',
        'last_credit_reset_at',
    ];

    protected function casts(): array
    {
        return [
            'last_credit_reset_at' => 'datetime',
            'plan_credits_balance' => 'integer',
            'plan_credits_used_this_month' => 'integer',
            'purchased_credits_balance' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'joined_at');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function executions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Execution::class,
            Project::class,
            'organization_id',
            'project_id'
        );
    }

    /**
     * O Admin mais antigo da organização (por `joined_at`).
     *
     * Não é um papel: `organization_user.role` só conhece `admin` e `member`
     * (SAD 20.4). "Owner" aqui é apenas apresentação — quem administra a
     * organização há mais tempo —, nunca critério de autorização.
     */
    public function owner(): User
    {
        return $this->users()
            ->wherePivot('role', OrganizationRole::Admin->value)
            ->orderBy('organization_user.joined_at')
            ->firstOrFail();
    }

    public function hasMember(User $user): bool
    {
        return $this->users()
            ->whereKey($user->id)
            ->exists();
    }

    public function roleOf(User $user): ?string
    {
        $pivot = $this->users()
            ->whereKey($user->id)
            ->first()
            ?->pivot;

        return $pivot?->role;
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('users', function (Builder $query) use ($userId) {
            $query->whereKey($userId);
        });
    }
}
