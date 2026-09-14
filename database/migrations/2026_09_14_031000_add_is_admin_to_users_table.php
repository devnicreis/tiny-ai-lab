<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.is_admin` — staff da plataforma Arkion (SAD 20.7, CLAUDE.md regra 22).
 *
 * É um boolean na própria tabela `users`, nunca uma tabela de papéis: hoje o
 * staff são duas pessoas. O eixo é global e da plataforma, e não se confunde em
 * nenhuma direção com `organization_user.role`, que é o papel dentro de uma
 * organização.
 *
 * A coluna nasce aqui porque a resposta de autenticação passa a declarar os dois
 * eixos separadamente (SAD 20.7): o cliente precisa saber que é staff sem que
 * isso implique papel algum em organização nenhuma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('firebase_uid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
