<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VideoReference extends Model
{
    protected $fillable = [
        'platform',
        'external_id',
        'url',
        'hook',
        'narrative_formula',
        'engagement_score',
        'niche',
    ];

    protected function casts(): array
    {
        return [
            'engagement_score' => 'integer',
        ];
    }

    public function scopeByNiche(Builder $query, string $niche): Builder
    {
        return $query->where('niche', $niche);
    }

    public function scopeHighEngagement(Builder $query, int $minScore = 1000): Builder
    {
        return $query->where('engagement_score', '>=', $minScore);
    }
}
