<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'parent_project_id',
        'brand_name',
        'website',
        'description',
        'segment',
        'logo_url',
        'competitors',
        'channels',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'competitors' => 'array',
            'channels' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class);
    }

    /**
     * A umbrella brand desta Brand, quando houver (SAD 19.2).
     *
     * `null` significa Brand de topo — o estado padrão.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'parent_project_id');
    }

    /**
     * As sub-brands abrigadas por esta Brand (SAD 19.2).
     *
     * Serve à apresentação — o aninhamento do BrandSwitcher. Nunca use esta
     * relação para ampliar o escopo de uma consulta de domínio: consultar a
     * umbrella brand não retorna dados das filhas (SAD 19.4, CLAUDE.md regra 29).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Project::class, 'parent_project_id');
    }

    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where('organization_id', $orgId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
