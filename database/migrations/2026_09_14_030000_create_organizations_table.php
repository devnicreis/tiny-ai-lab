<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entidade de faturamento da plataforma (SAD 4.15 e 20.1).
     *
     * A Organização — não o usuário — é dona do plano, das Marcas e das duas
     * carteiras de crédito da operação (SAD 12.2). `users` mantém apenas a
     * carteira pessoal.
     *
     * `plan` é NULLABLE por decisão confirmada: toda organização nasce sem
     * assinatura, inclusive a "Personal" automática, e `null` significa
     * exatamente isso (SAD 12.18). Nenhum plano default é atribuído aqui nem
     * em seeder.
     *
     * Migration incremental (SAD K.7): nenhuma migration anterior é alterada.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);
            $table->string('slug')->unique();

            $table->enum('plan', ['copy_briefing', 'studio_visual', 'full_pass'])
                ->nullable();

            $table->unsignedInteger('plan_credits_balance')->default(0);
            $table->unsignedInteger('plan_credits_used_this_month')->default(0);
            $table->unsignedInteger('purchased_credits_balance')->default(0);

            $table->timestamp('last_credit_reset_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
