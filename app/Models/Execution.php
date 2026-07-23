<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Execution extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'pipeline_type',
        'status',
        'config',
        'result',
        'credits_consumed',
        'tokens_used',
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
