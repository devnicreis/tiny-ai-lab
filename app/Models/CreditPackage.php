<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pacote de créditos avulsos do catálogo (SAD 12.9).
 *
 * Fonte oficial dos preços e quantidades vendidas fora da assinatura, no mesmo
 * papel que `plan_limits` cumpre para o custo das ações: nenhum valor de
 * crédito ou de preço pode ser codificado na aplicação (CLAUDE.md regra 9).
 *
 * Créditos comprados por um pacote sempre creditam
 * `users.purchased_credits_balance` — jamais `plan_credits_balance`, que
 * expira na virada do ciclo (SAD 12.2).
 */
class CreditPackage extends Model
{
    protected $fillable = [
        'name',
        'credits',
        'price_cents',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Pacotes visíveis no catálogo.
     *
     * Um pacote desativado nunca é excluído — compras históricas mantêm a
     * referência (SAD 12.9) —, apenas deixa de ser oferecido.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
