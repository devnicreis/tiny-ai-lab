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
        Schema::create('video_references', function (Blueprint $table) {
            $table->id();

            $table->string('platform');
            $table->string('external_id');
            $table->string('url');
            $table->text('hook');
            $table->text('narrative_formula');
            $table->unsignedInteger('engagement_score')->default(0);
            $table->string('niche');

            $table->timestamps();

            $table->unique(['platform', 'external_id']);
            $table->index('niche');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_references');
    }
};
