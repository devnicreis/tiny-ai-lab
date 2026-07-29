<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consumo mensal acumulado de créditos (SAD 4.3 e 12.4).
 *
 * A coluna acompanha `credits_balance` mas responde a outra pergunta: o saldo
 * diz quanto resta, o contador diz quanto já foi gasto no mês corrente. Ambos
 * são escritos pelo CreditService no mesmo débito, dentro da mesma transação,
 * de modo que nunca divergem.
 *
 * Migration incremental (SAD K.7): a criação original de `users` permanece
 * intocada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('credits_used_this_month')
                ->default(0)
                ->after('credits_balance');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('credits_used_this_month');
        });
    }
};
