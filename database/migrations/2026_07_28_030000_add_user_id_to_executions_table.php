<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O SAD (4.5) especifica `user_id` em `executions`, mas a migration inicial
 * criou apenas `project_id`. A coluna é necessária para a autorização do canal
 * privado `execution.{id}` (SAD 9.5), que resolve o dono da execução
 * diretamente pelo usuário.
 *
 * O escopo por Brand continua sendo `project_id`; `user_id` é apenas a
 * denormalização do dono, derivada de `projects.user_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });

        // Backfill das execuções existentes a partir do dono da Brand.
        DB::table('executions')->whereNull('user_id')->update([
            'user_id' => DB::raw(
                '(select user_id from projects where projects.id = executions.project_id)'
            ),
        ]);

        Schema::table('executions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
