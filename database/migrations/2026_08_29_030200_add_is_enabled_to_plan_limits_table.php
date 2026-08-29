<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suporte de dados ao feature gating por plano (SAD 12.5).
     *
     * O gating é um eixo independente do saldo: a linha `(plan, action)` passa
     * a dizer não apenas quanto a ação custa, mas se o plano pode executá-la.
     *
     * `default(true)` mantém o comportamento vigente para toda linha existente
     * — hoje qualquer plano acessa qualquer pipeline (SAD 12.15) — de modo que
     * a migration é retrocompatível e não exige backfill.
     */
    public function up(): void
    {
        Schema::table('plan_limits', function (Blueprint $table) {
            $table->boolean('is_enabled')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_limits', function (Blueprint $table) {
            $table->dropColumn('is_enabled');
        });
    }
};
