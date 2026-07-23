<?php

namespace Database\Seeders;

use App\Models\Execution;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $user = User::factory()->pro()->create([
            'firebase_uid' => 'dev-user-001',
        ]);

        $projects = Project::factory()
            ->count(2)
            ->for($user)
            ->create();

        Execution::factory()
            ->count(3)
            ->completed()
            ->for($projects->first())
            ->create();

        Execution::factory()
            ->failed()
            ->for($projects->last())
            ->create();
    }
}
