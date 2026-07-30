<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Execution extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Pipelines que a API aceita (SAD 6 e 12.3).
     *
     * A lista espelha exatamente as actions de consumo registradas em
     * `plan_limits`; `total_credits` fica de fora por descrever o teto do plano,
     * não um pipeline. O custo de cada uma continua vindo do banco — nenhum
     * valor de crédito é conhecido aqui (CLAUDE.md regra 9).
     *
     * @var array<int, string>
     */
    public const PIPELINE_TYPES = [
        'brand_research',
        'briefing_generation',
        'image_generation',
        'copywriting_edit',
        'seo_optimization',
        'create_article',
        'create_post',
        'create_ad',
    ];

    /**
     * Estados finais de uma Execution (SAD 6 e CLAUDE.md §6).
     *
     * Alcançados exclusivamente pelo callback autenticado do n8n ou pelo
     * `failed()` do Job; nenhuma tentativa posterior pode sobrescrevê-los.
     *
     * @var array<int, string>
     */
    public const TERMINAL_STATUSES = ['completed', 'failed'];

    protected $fillable = [
        'project_id',
        'user_id',
        'pipeline_type',
        'status',
        'config',
        'result',
        'credits_consumed',
        'tokens_used',
        'completed_at',
        'failed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'result' => 'array',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForBrand(
        Builder $query,
        int $projectId
    ): Builder {
        return $query->where(
            'project_id',
            $projectId
        );
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }
}
