<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convites para participar de uma organização (SAD 20.4 e 20.8).
     *
     * O token em claro nunca é persistido: existe apenas no link entregue ao
     * convidado. A tabela guarda só o hash sha256 — um vazamento do banco não
     * permite aceitar convite de ninguém.
     *
     * `role` nunca é `owner`: ninguém entra como Owner, a posse só muda por
     * transferência (SAD 20.4, invariante 3).
     */
    public function up(): void
    {
        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('email');
            $table->string('role');
            $table->string('token_hash')->unique();

            $table->foreignId('invited_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // Um convite pendente por e-mail em cada organização. Índice de
        // expressão parcial: o Schema Builder não o expressa, e a sintaxe é a
        // mesma no PostgreSQL e no SQLite.
        DB::statement(
            'CREATE UNIQUE INDEX organization_invitations_pending_email_unique ON organization_invitations (organization_id, lower(email)) WHERE accepted_at IS NULL AND revoked_at IS NULL;'
        );

        // O CHECK só no PostgreSQL: no SQLite exigiria reconstruir a tabela, e o
        // domínio já recusa `owner` antes da escrita.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE organization_invitations ADD CONSTRAINT organization_invitations_role_check CHECK (role IN ('admin', 'member'));"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
