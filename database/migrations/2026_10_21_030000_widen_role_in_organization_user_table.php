<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `organization_user.role` passa a aceitar três valores — `owner`,
     * `admin` e `member` (ADR-010, SAD 4.15 e 20.4).
     *
     * A coluna nasceu como `enum('role', ['admin', 'member'])`. O enum do
     * Laravel é `varchar` + CHECK, e `enum()->change()` gera no PostgreSQL um
     * ALTER com CHECK embutido que o banco não aceita (mesmo problema de
     * `2026_09_14_030700_widen_plan_in_plan_limits_table`). A coluna vira
     * `string` e, no PostgreSQL, a restrição é recriada com os três valores.
     *
     * No SQLite (suíte de testes) a conversão reconstrói a tabela sem o CHECK;
     * lá a restrição fica apenas no domínio (Enum `OrganizationRole`).
     *
     * Migration incremental (SAD K.7): a migration de criação da tabela não
     * é alterada.
     */
    public function up(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            $table->string('role')->change();
        });

        // A conversão de tipo não remove o CHECK no PostgreSQL, que
        // continuaria recusando `owner`.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE organization_user DROP CONSTRAINT IF EXISTS organization_user_role_check;');
            DB::statement(
                "ALTER TABLE organization_user ADD CONSTRAINT organization_user_role_check CHECK (role IN ('owner', 'admin', 'member'));"
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * Pressupõe que nenhum `owner` reste na tabela: o `down()` de
     * `promote_single_admin_to_owner`, que roda antes deste, devolve todo
     * Owner para `admin`.
     */
    public function down(): void
    {
        // No PostgreSQL a coluna já é o `varchar(255)` do enum original: basta
        // restaurar a restrição. `enum()->change()` não é opção (ver `up()`).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE organization_user DROP CONSTRAINT IF EXISTS organization_user_role_check;');
            DB::statement(
                "ALTER TABLE organization_user ADD CONSTRAINT organization_user_role_check CHECK (role IN ('admin', 'member'));"
            );

            return;
        }

        Schema::table('organization_user', function (Blueprint $table) {
            $table->enum('role', ['admin', 'member'])->change();
        });
    }
};
