<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hierarquia de marcas — umbrella brands (SAD 19.2).
     *
     * Auto-referência nullable: `null` é Brand de topo, e é o estado de toda
     * Brand criada antes desta coluna, de modo que a introdução é
     * retrocompatível e não exige backfill (SAD 19.6).
     *
     * `nullOnDelete()` segue a política do Apêndice K.4 para relacionamentos
     * opcionais: excluir a umbrella brand promove as filhas a Brands de topo em
     * vez de apagá-las. A política definitiva de cascata permanece [A DEFINIR]
     * (SAD 19.2) — a escolha aqui é a que não destrói dados.
     *
     * A FK garante apenas que o pai existe. Que ele pertença ao mesmo usuário é
     * responsabilidade da ProjectPolicy e do Form Request (SAD 19.2), não do
     * banco.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('parent_project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_project_id');
        });
    }
};
