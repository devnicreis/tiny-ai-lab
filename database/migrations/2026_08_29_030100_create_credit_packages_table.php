<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de pacotes de crédito avulso (SAD 12.9).
     *
     * Existe pelo mesmo motivo de `plan_limits`: preço e quantidade são
     * decisão comercial e precisam mudar sem deploy.
     *
     * Sem SoftDeletes por decisão de domínio — um pacote sai do catálogo por
     * `is_active = false`, nunca por exclusão: compras históricas mantêm a
     * referência.
     *
     * `sort_order` e `external_price_id`, previstos na Seção 12.9, ficam de
     * fora enquanto o gateway de pagamento permanece [A DEFINIR] (SAD 12.13):
     * o formato do identificador externo depende dessa escolha.
     */
    public function up(): void
    {
        Schema::create('credit_packages', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->unsignedInteger('credits');
            $table->unsignedInteger('price_cents');
            $table->string('currency', 3)->default('BRL');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_packages');
    }
};
