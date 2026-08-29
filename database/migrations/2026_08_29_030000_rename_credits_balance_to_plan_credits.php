<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separa o saldo único em dois saldos independentes (SAD 12.2).
     *
     * `credits_balance` é renomeada — e não recriada — porque o saldo já
     * existente é, por definição, crédito de plano: renomear preserva os dados
     * sem exigir backfill e sem janela em que o saldo do usuário fique zerado.
     *
     * `purchased_credits_balance` nasce em zero: nenhuma compra avulsa existia
     * antes desta migration.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('credits_balance', 'plan_credits_balance');
        });

        // Blueprint separado: o SQLite recria a tabela a cada rename, e a nova
        // coluna precisa enxergar o schema já renomeado.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('purchased_credits_balance')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('purchased_credits_balance');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('plan_credits_balance', 'credits_balance');
        });
    }
};
