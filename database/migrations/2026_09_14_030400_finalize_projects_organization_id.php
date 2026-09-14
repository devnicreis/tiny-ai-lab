<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fecha a migração de propriedade: a Marca passa a pertencer à Organização
     * e o vínculo direto User → Project deixa de existir (SAD 20.2).
     *
     * Depende do backfill imediatamente anterior: o NOT NULL só é aplicável
     * depois que toda Marca existente foi adotada por uma organização.
     *
     * `projects.user_id` é removida em vez de mantida como auditoria. A
     * `CLAUDE.md` §30 admite preservá-la com esse significado, mas manter uma
     * coluna que já foi critério de autorização é convite a regressão: o
     * escopo agora é `organization_id` (SAD 20.6) e nada no código deve poder
     * voltar a filtrar por usuário.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * `user_id` é restaurada NULLABLE, não NOT NULL como na criação original:
     * o dono original de cada Marca não é recuperável em geral. O valor é
     * reconstruído a partir do Admin da organização dona — o inverso natural
     * do backfill —, o que cobre toda Marca cujo organização tenha Admin, mas
     * não é garantia suficiente para reimpor o NOT NULL.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->index('user_id');
        });

        DB::table('projects')->chunkById(200, function ($projects) {
            foreach ($projects as $project) {
                if ($project->user_id !== null || $project->organization_id === null) {
                    continue;
                }

                $userId = DB::table('organization_user')
                    ->where('organization_id', $project->organization_id)
                    ->where('role', 'admin')
                    ->orderBy('user_id')
                    ->value('user_id');

                if ($userId === null) {
                    continue;
                }

                DB::table('projects')
                    ->where('id', $project->id)
                    ->update(['user_id' => $userId]);
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->change();
        });
    }
};
