<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\Execution;
use App\Models\Organization;
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

        $owner = User::factory()->create([
            'firebase_uid' => 'dev-user-001',
        ]);

        $member = User::factory()->create([
            'firebase_uid' => 'dev-user-002',
        ]);

        $organization = $this->createPersonalOrganization($owner, $member);

        // As duas Brands vivem na mesma Organização: é ela a dona das Marcas
        // e o escopo de tudo que o usuário enxerga (SAD 20.2 e 20.6).
        $projects = Project::factory()
            ->count(2)
            ->create(['organization_id' => $organization->id]);

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

    /**
     * Organização "Personal" do usuário de desenvolvimento, com ele como
     * Admin, e um segundo usuário como Member (SAD 20.3 e 20.4).
     *
     * Nasce sem plano, como qualquer organização (SAD 12.18 e 20.3): nenhum
     * plano default é atribuído em seeder.
     */
    private function createPersonalOrganization(User $owner, User $member): Organization
    {
        $organization = Organization::factory()->create([
            'name' => $owner->name.' Personal',
            'slug' => 'personal-dev-001',
        ]);

        $organization->users()->attach($owner->id, [
            'role' => OrganizationRole::Admin->value,
            'joined_at' => now(),
        ]);

        $organization->users()->attach($member->id, [
            'role' => OrganizationRole::Member->value,
            'joined_at' => now(),
        ]);

        return $organization;
    }
}
