<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proveniência de criação da Marca (Apêndice K.4, relacionamento
     * opcional): quem criou o `project`, sem qualquer papel de autorização —
     * o escopo continua exclusivamente por `organization_id` (SAD 20.6).
     *
     * `nullOnDelete()`: se o criador for removido, a Marca permanece intacta,
     * apenas sem proveniência.
     *
     * O relacionamento `creator()` no Model fica para a Sessão 4.0c.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        // Backfill: o criador presumido é o Admin da organização dona da
        // Marca — o primeiro por `joined_at` quando houver mais de um.
        DB::table('projects')->orderBy('id')->chunkById(200, function ($projects) {
            foreach ($projects as $project) {
                $adminUserId = DB::table('organization_user')
                    ->where('organization_id', $project->organization_id)
                    ->where('role', 'admin')
                    ->orderBy('joined_at')
                    ->value('user_id');

                if ($adminUserId === null) {
                    continue;
                }

                DB::table('projects')
                    ->where('id', $project->id)
                    ->update(['created_by_user_id' => $adminUserId]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
