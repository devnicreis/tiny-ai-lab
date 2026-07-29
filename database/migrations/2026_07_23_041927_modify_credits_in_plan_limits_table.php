<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite não suporta ALTER COLUMN DROP NOT NULL; a suíte de testes não
        // depende disso, pois cobre o cenário enterprise via mock de
        // PlanLimit::forAction() retornando null.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE plan_limits ALTER COLUMN credits DROP NOT NULL;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE plan_limits ALTER COLUMN credits SET NOT NULL;');
        }
    }
};
