<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula toda Marca à Organização dona (SAD 20.2).
     *
     * NULLABLE apenas nesta migration: a coluna precisa existir vazia para que
     * o backfill (`backfill_organizations_from_users`) possa preenchê-la. O
     * NOT NULL definitivo é aplicado em `finalize_projects_organization_id`,
     * depois do backfill — a regra absoluta "nenhuma Marca existe fora de uma
     * Organização" vale a partir de lá.
     *
     * `cascadeOnDelete()` segue o Apêndice K.4: a Marca pertence exclusivamente
     * à Organização. A política definitiva de exclusão de organização com
     * Marcas e Executions permanece [A DEFINIR] (SAD 20.8).
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
