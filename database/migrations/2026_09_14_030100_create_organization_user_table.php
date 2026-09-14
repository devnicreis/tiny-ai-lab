<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Participação de um usuário em uma organização, com o papel dele nessa
     * organização (SAD 4.15 e 20.4).
     *
     * Nome da tabela em ordem alfabética dos modelos singulares, conforme a
     * convenção de pivots do Apêndice K.1: `organization_user`.
     *
     * `role` tem exatamente dois valores — `admin` e `member`. Não existe
     * `owner`: o terceiro papel exige ADR (SAD 20.4).
     *
     * O invariante "a organização nunca fica sem Admin" (SAD 20.4) é de
     * domínio e não é expressável em constraint — o banco garante apenas um
     * papel por usuário em cada organização.
     */
    public function up(): void
    {
        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('role', ['admin', 'member']);

            // NOT NULL conforme especificado; `useCurrent()` evita que uma
            // inserção sem o campo explícito quebre antes de existir Model.
            $table->timestamp('joined_at')->useCurrent();

            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};
