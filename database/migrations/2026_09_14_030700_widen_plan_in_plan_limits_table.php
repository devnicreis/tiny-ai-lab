<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `plan_limits.plan` deixa de ser um enum e passa a ser texto livre.
     *
     * O feature gating é do plano **da organização** (SAD 12.5), e
     * `organizations.plan` guarda os planos comerciais da Seção 12.6 —
     * `copy_briefing`, `studio_visual`, `full_pass`. A coluna daqui nasceu com
     * o enum legado `free | pro | enterprise`, herdado do modelo em que o plano
     * vivia no usuário: sem esta migration nenhuma linha de gating pode sequer
     * ser gravada para um plano real, e a verificação da Seção 12.5 seria
     * inalcançável por construção.
     *
     * A coluna vira `string` em vez de um enum novo porque o catálogo comercial
     * é configuração de operação, não de schema: a Seção 12.14 exige que
     * mudanças comerciais aconteçam sem deploy, e um enum obrigaria uma
     * migration a cada plano criado ou renomeado. A restrição real continua
     * sendo a chave `(plan, action)` e o enum de `organizations.plan`, que é
     * quem de fato decide quais planos existem.
     *
     * Nenhuma linha é convertida: as linhas legadas (`free`, `pro`,
     * `enterprise`) continuam válidas como texto. Mapeá-las para os planos
     * comerciais exigiria conhecer os créditos mensais de cada plano, que
     * permanecem **[A DEFINIR]** na Seção 12.6 e não podem ser inferidos.
     *
     * Migration incremental (SAD K.7): nenhuma migration anterior é alterada.
     */
    public function up(): void
    {
        Schema::table('plan_limits', function (Blueprint $table) {
            $table->string('plan')->change();
        });

        // O enum do Laravel é `varchar` + CHECK; no PostgreSQL a conversão de
        // tipo não remove a restrição, que continuaria recusando os planos
        // comerciais.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE plan_limits DROP CONSTRAINT IF EXISTS plan_limits_plan_check;');
        }
    }

    /**
     * Reverse the migrations.
     *
     * O enum legado volta como estava. Linhas gravadas com um plano comercial
     * são removidas antes, porque nenhuma delas caberia na restrição — e
     * mantê-las abortaria o `down()` inteiro.
     */
    public function down(): void
    {
        DB::table('plan_limits')
            ->whereNotIn('plan', ['free', 'pro', 'enterprise'])
            ->delete();

        // No PostgreSQL o enum já é o `varchar(255)` que está lá: o que falta é
        // apenas a restrição. Reconstruir a coluna pelo Schema Builder não é
        // opção — `enum()->change()` gera um ALTER com CHECK embutido, sintaxe
        // que o PostgreSQL não aceita.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE plan_limits ADD CONSTRAINT plan_limits_plan_check CHECK (plan IN ('free', 'pro', 'enterprise'));"
            );

            return;
        }

        Schema::table('plan_limits', function (Blueprint $table) {
            $table->enum('plan', ['free', 'pro', 'enterprise'])->change();
        });
    }
};
