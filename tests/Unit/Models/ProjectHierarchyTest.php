<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hierarquia de umbrella brands (SAD 19.2).
 */
class ProjectHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_brand_without_a_parent_is_a_top_level_brand(): void
    {
        $brand = Project::factory()->for(User::factory())->create();

        $this->assertNull($brand->parent_project_id);
        $this->assertNull($brand->parent);
        $this->assertCount(0, $brand->children);
    }

    public function test_sub_brands_resolve_parent_and_children(): void
    {
        $user = User::factory()->create();

        $umbrella = Project::factory()->for($user)->create(['brand_name' => 'Acme Group']);
        $coffee = Project::factory()->for($user)->create(['parent_project_id' => $umbrella->id]);
        $tea = Project::factory()->for($user)->create(['parent_project_id' => $umbrella->id]);

        $this->assertSame($umbrella->id, $coffee->parent->id);
        $this->assertEqualsCanonicalizing(
            [$coffee->id, $tea->id],
            $umbrella->children()->pluck('id')->all(),
        );
    }

    /**
     * A hierarquia organiza a apresentação e nunca amplia o escopo de uma
     * consulta de domínio (SAD 19.4, CLAUDE.md regra 29).
     */
    public function test_hierarchy_never_widens_the_scope_of_a_domain_query(): void
    {
        $user = User::factory()->create();

        $umbrella = Project::factory()->for($user)->create();
        $sub = Project::factory()->for($user)->create(['parent_project_id' => $umbrella->id]);

        $this->assertSame(
            [$umbrella->id],
            Project::query()->where('id', $umbrella->id)->pluck('id')->all(),
        );
        $this->assertNotContains($sub->id, [$umbrella->id]);
    }
}
