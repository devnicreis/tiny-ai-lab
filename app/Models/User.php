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
use Illuminate\Support\Str;
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
            'is_admin' => 'boolean',
            'purchased_credits_balance' => 'integer',
            'has_completed_setup' => 'boolean',
            'notify_on_completion' => 'boolean',
        ];
    }

    /**
     * Staff da plataforma Arkion (SAD 20.7).
     *
     * Eixo global, do painel `/admin/*`. Não concede papel algum em organização
     * nenhuma e nunca pode ser usado como atalho para `roleIn()` — nem o
     * contrário. `is_admin` fica deliberadamente fora de `$fillable`: staff da
     * plataforma jamais se define por entrada de requisição.
     */
    public function isPlatformStaff(): bool
    {
        return (bool) $this->is_admin;
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

    /**
     * Até duas iniciais do nome, para o avatar textual da interface.
     */
    public function initials(): string
    {
        return mb_substr(Str::initials($this->name, true), 0, 2);
    }
}
