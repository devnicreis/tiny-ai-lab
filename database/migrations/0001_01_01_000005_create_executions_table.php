<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('pipeline_type');

            $table->string('status')->index();

            $table->jsonb('config');

            $table->jsonb('result')->nullable();

            $table->unsignedInteger('tokens_used')->default(0);

            $table->unsignedInteger('credits_consumed');

            $table->timestamp('completed_at')->nullable();

            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index(['project_id', 'status']);

            $table->index(['project_id', 'created_at']);
        });

        DB::statement(
            'CREATE INDEX executions_config_gin
             ON executions
             USING GIN (config);'
        );

        DB::statement(
            'CREATE INDEX executions_result_gin
             ON executions
             USING GIN (result);'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('executions');
    }
};
