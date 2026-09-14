<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O faturamento sai de `users` e passa a viver em `organizations`
     * (SAD 12.2 e 20.1). Depois desta migration nenhuma coluna de plano ou de
     * saldo de plano existe em `users`.
     *
     * Permanecem, deliberadamente:
     *
     * - `purchased_credits_balance` — a carteira pessoal, terceira carteira da
     *   ordem de débito da `CLAUDE.md` §27, que acompanha o usuário entre
     *   organizações e nunca pertenceu à operação;
     * - `is_admin` — staff da plataforma Arkion, eixo de permissão global e
     *   completamente independente de `organization_user.role` (SAD 20.7).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'plan',
                'plan_credits_balance',
                'credits_used_this_month',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * As organizações ainda existem quando este `down()` roda — é o primeiro a
     * ser desfeito na sequência —, então o saldo e o consumo do mês são
     * devolvidos ao usuário a partir da organização em que ele é Admin. `plan`
     * volta ao default `free` da criação original: os planos comerciais de
     * `organizations.plan` não têm correspondência no enum legado.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('plan', ['free', 'pro', 'enterprise'])->default('free');
            $table->unsignedInteger('plan_credits_balance')->default(0);
            $table->unsignedInteger('credits_used_this_month')->default(0);
        });

        if (! Schema::hasTable('organizations') || ! Schema::hasTable('organization_user')) {
            return;
        }

        DB::table('users')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                $organization = DB::table('organization_user')
                    ->join('organizations', 'organizations.id', '=', 'organization_user.organization_id')
                    ->where('organization_user.user_id', $user->id)
                    ->where('organization_user.role', 'admin')
                    ->orderBy('organizations.id')
                    ->first([
                        'organizations.plan_credits_balance',
                        'organizations.plan_credits_used_this_month',
                    ]);

                if ($organization === null) {
                    continue;
                }

                DB::table('users')->where('id', $user->id)->update([
                    'plan_credits_balance' => $organization->plan_credits_balance,
                    'credits_used_this_month' => $organization->plan_credits_used_this_month,
                ]);
            }
        });
    }
};
