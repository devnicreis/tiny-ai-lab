<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plan_limits', function (Blueprint $table) {
            $table->id();

            $table->enum('plan', ['free', 'pro', 'enterprise']);
            $table->string('action');
            $table->unsignedInteger('credits');

            $table->timestamps();

            $table->unique(['plan', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_limits');
    }
};
