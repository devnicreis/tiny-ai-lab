<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Índice único parcial `UNIQUE(organization_id) WHERE role = 'owner'`
     * em `organization_user` (SAD 4.15; SAD 20.4, invariante 1).
     *
     * Garante o teto do invariante do Owner: nenhuma organização pode ter
     * mais de um. O piso — pelo menos um — é verificado no domínio, dentro
     * da transação.
     *
     * O Schema Builder não expressa índice parcial, por isso `DB::statement`;
     * a sintaxe é a mesma no PostgreSQL e no SQLite. Roda depois de
     * `promote_single_admin_to_owner`, que deixa no máximo um Owner por
     * organização.
     *
     * Atenção no SQLite: o `->change()` do Laravel reconstrói a tabela e
     * recria os índices sem a cláusula WHERE. Uma migration futura que altere
     * colunas de `organization_user` precisa recriar este índice.
     */
    public function up(): void
    {
        DB::statement(
            "CREATE UNIQUE INDEX organization_user_single_owner_unique ON organization_user (organization_id) WHERE role = 'owner';"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS organization_user_single_owner_unique;');
    }
};
