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

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
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
     * O Owner da organização — o papel persistido, nunca derivado (ADR-010).
     *
     * Toda organização tem exatamente um Owner (SAD 20.4): o índice único
     * parcial garante o teto, e as operações de participação garantem o piso.
     * `firstOrFail()` torna visível, em vez de silencioso, o estado inválido de
     * uma organização sem Owner.
     */
    public function owner(): User
    {
        return $this->users()
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->firstOrFail();
    }

    /**
     * Quantos Owners a organização tem — 1 em todo estado válido.
     *
     * Existe para a verificação do piso dentro da transação que altera
     * participação (SAD 20.4, invariante 1); fora dela a leitura não protege
     * nada contra operações concorrentes.
     */
    public function ownerCount(): int
    {
        return $this->users()
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->count();
    }

    public function isOwner(User $user): bool
    {
        return $this->users()
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->whereKey($user->id)
            ->exists();
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
