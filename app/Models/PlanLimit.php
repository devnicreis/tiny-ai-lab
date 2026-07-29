<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlanLimit extends Model
{
    protected $fillable = [
        'plan',
        'action',
        'credits',
    ];

    public static function forAction(string $action): ?int
    {
        return Cache::remember(
            "plan_limits:{$action}",
            now()->addMinutes(60),
            fn () => static::where('action', $action)->value('credits')
        );
    }
}
