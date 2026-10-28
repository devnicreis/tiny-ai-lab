<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Livewire\Header;
use App\Livewire\Sidebar;
use App\Livewire\SwitchOrganizationModal;
use App\Models\Organization;
use App\Models\PlanLimit;
use App\Models\User;
use App\Services\BrandContext;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithOrganizations;
use Tests\TestCase;

/**
 * Shell autenticado — Sidebar, Header e seletores (Sessão 3.2).
 *
 * Os componentes só apresentam: cada teste de ação confere que a decisão foi
 * do Service (`OrganizationContext`, `BrandContext`) e da Policy, e não do
 * componente (SAD M.9).
 */
class AppShellTest extends TestCase
{
    use InteractsWithOrganizations, RefreshDatabase;

    private function actingInOrganization(User $user, Organization $organization): void
    {
        $this->actingAs($user);

        session([OrganizationContext::SESSION_KEY => $organization->id]);
    }

    /**
     * @return array<string, array{OrganizationRole}>
     */
    public static function ownerAndMember(): array
    {
        return [
            'owner' => [OrganizationRole::Owner],
            'member' => [OrganizationRole::Member],
        ];
    }

    // ---------------------------------------------------------------------
    // Seletor de organização
    // ---------------------------------------------------------------------

    public function test_switch_organization_lists_each_organization_with_its_role(): void
    {
        $user = User::factory()->create();

        $owned = $this->organizationWith($user, OrganizationRole::Owner, ['name' => 'Alpha Studio']);
        $this->organizationWith($user, OrganizationRole::Admin, ['name' => 'Beta Agency']);
        $member = $this->organizationWith($user, OrganizationRole::Member, ['name' => 'Gamma Group']);

        $this->brandOf($owned);
        $this->brandOf($owned);
        $this->brandOf($member);

        Organization::factory()->withOwner()->create(['name' => 'Foreign Holdings']);

        $this->actingInOrganization($user, $owned);

        $component = Livewire::test(SwitchOrganizationModal::class);

        $organizations = collect($component->instance()->organizations)->keyBy('name');

        $this->assertSame(['Alpha Studio', 'Beta Agency', 'Gamma Group'], $organizations->keys()->all());

        $this->assertSame('Owner', $organizations['Alpha Studio']['role']);
        $this->assertSame('Admin', $organizations['Beta Agency']['role']);
        $this->assertSame('Member', $organizations['Gamma Group']['role']);

        $this->assertSame(2, $organizations['Alpha Studio']['brands']);
        $this->assertSame(1, $organizations['Gamma Group']['brands']);

        $this->assertTrue($organizations['Alpha Studio']['current']);
        $this->assertFalse($organizations['Beta Agency']['current']);

        $component
            ->assertSee('Owner • 2 brands')
            ->assertSee('Admin • 0 brands')
            ->assertSee('Member • 1 brand')
            ->assertSee('Current')
            ->assertDontSee('Foreign Holdings');
    }

    public function test_switching_to_an_organization_the_user_does_not_belong_to_is_refused(): void
    {
        $user = User::factory()->create();
        $own = $this->organizationWith($user);
        $foreign = Organization::factory()->withOwner()->create();

        $this->actingInOrganization($user, $own);

        Livewire::test(SwitchOrganizationModal::class)
            ->call('switchTo', $foreign->id)
            ->assertForbidden();

        // Inexistente responde igual a alheia: o id não revela nada.
        Livewire::test(SwitchOrganizationModal::class)
            ->call('switchTo', $foreign->id + 1000)
            ->assertForbidden();

        $this->assertSame($own->id, session(OrganizationContext::SESSION_KEY));
    }

    public function test_switching_organization_clears_the_active_brand_and_goes_to_the_dashboard(): void
    {
        $user = User::factory()->create();
        $current = $this->organizationWith($user);
        $other = $this->organizationWith($user, OrganizationRole::Member);

        $this->actingInOrganization($user, $current);
        session([OrganizationContext::BRAND_SESSION_KEY => $this->brandOf($current)->id]);

        Livewire::test(SwitchOrganizationModal::class)
            ->call('switchTo', $other->id)
            ->assertRedirect(route('dashboard'));

        $this->assertSame($other->id, session(OrganizationContext::SESSION_KEY));
        $this->assertNull(session(OrganizationContext::BRAND_SESSION_KEY));
    }

    public function test_switch_organization_is_offered_even_with_a_single_organization(): void
    {
        $user = User::factory()->create();
        $organization = $this->organizationWith($user);

        $this->actingInOrganization($user, $organization);

        Livewire::test(Header::class)->assertSee('Switch Organization');
    }

    public function test_select_page_reuses_the_organization_list(): void
    {
        $user = User::factory()->create();
        $this->organizationWith($user, OrganizationRole::Owner, ['name' => 'Alpha Studio']);
        $this->organizationWith($user, OrganizationRole::Member, ['name' => 'Gamma Group']);

        // Duas organizações e nenhuma ativa: o middleware manda para cá.
        $this->actingAs($user)->get('/briefing')->assertRedirect(route('organizations.select'));

        $this->get('/organizations/select')
            ->assertOk()
            ->assertSee('Alpha Studio')
            ->assertSee('Owner • 0 brands')
            ->assertSee('Member • 0 brands')
            ->assertSee('Manage all organizations')
            ->assertSee('+ Create new organization');
    }

    public function test_manage_all_organizations_is_a_neutral_placeholder(): void
    {
        $user = User::factory()->create();
        $this->organizationWith($user);

        $this->actingAs($user)->get('/settings/organizations')
            ->assertOk()
            ->assertSee('Managing organizations is not available yet.');
    }

    // ---------------------------------------------------------------------
    // Menu da marca
    // ---------------------------------------------------------------------

    public function test_member_does_not_see_add_new_brand(): void
    {
        $member = User::factory()->create();
        $organization = $this->organizationWith($member, OrganizationRole::Member);

        $this->actingInOrganization($member, $organization);

        Livewire::test(Header::class)->assertDontSee('Add new Brand');
    }

    public function test_owner_and_admin_see_add_new_brand(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationWith($owner);
        $admin = $this->adminOf($organization);

        foreach ([$owner, $admin] as $user) {
            $this->actingInOrganization($user, $organization);

            Livewire::test(Header::class)->assertSee('Add new Brand');
        }
    }

    public function test_brand_menu_lists_brands_of_the_active_organization_with_sub_brand_caption(): void
    {
        $user = User::factory()->create();
        $organization = $this->organizationWith($user, OrganizationRole::Owner, ['name' => 'Acme Inc']);

        $umbrella = $this->brandOf($organization, ['brand_name' => 'Acme Group']);
        $this->brandOf($organization, ['brand_name' => 'Acme Coffee', 'parent_project_id' => $umbrella->id]);

        $other = $this->organizationWith($user, OrganizationRole::Member);
        $this->brandOf($other, ['brand_name' => 'Northwind']);

        $this->actingInOrganization($user, $organization);

        Livewire::test(Header::class)
            ->assertSee('Acme Inc')
            ->assertSee('Acme Group')
            ->assertSee('Acme Coffee')
            ->assertSee('Sub-brand of Acme Group')
            ->assertDontSee('Northwind');
    }

    public function test_switching_brand_within_the_active_organization_updates_the_session(): void
    {
        $user = User::factory()->create();
        $organization = $this->organizationWith($user, OrganizationRole::Member);
        $this->brandOf($organization);
        $target = $this->brandOf($organization, ['brand_name' => 'Target Brand']);

        $this->actingInOrganization($user, $organization);

        Livewire::test(Header::class)
            ->call('switchBrand', $target->id)
            ->assertDispatched('brand-switched', projectId: $target->id)
            ->assertSee('Target Brand');

        $this->assertSame($target->id, session(OrganizationContext::BRAND_SESSION_KEY));
    }

    /**
     * Membro das duas organizações, a Policy deixaria ver a Brand da outra —
     * mas ela não pertence à organização **ativa** (SAD 20.5).
     */
    public function test_switching_to_a_brand_of_another_organization_is_refused(): void
    {
        $user = User::factory()->create();
        $active = $this->organizationWith($user);
        $current = $this->brandOf($active);

        $other = $this->organizationWith($user, OrganizationRole::Member);
        $foreignBrand = $this->brandOf($other);

        $this->actingInOrganization($user, $active);
        session([OrganizationContext::BRAND_SESSION_KEY => $current->id]);

        Livewire::test(Header::class)
            ->call('switchBrand', $foreignBrand->id)
            ->assertForbidden();

        $this->assertSame($current->id, session(OrganizationContext::BRAND_SESSION_KEY));
    }

    public function test_active_brand_outside_the_active_organization_is_discarded(): void
    {
        $user = User::factory()->create();
        $active = $this->organizationWith($user);
        $other = $this->organizationWith($user, OrganizationRole::Member);

        $this->actingInOrganization($user, $active);
        session([OrganizationContext::BRAND_SESSION_KEY => $this->brandOf($other)->id]);

        $this->assertNull(app(BrandContext::class)->current());
        $this->assertNull(session(OrganizationContext::BRAND_SESSION_KEY));
    }

    public function test_single_brand_of_the_organization_becomes_active(): void
    {
        $user = User::factory()->create();
        $organization = $this->organizationWith($user);
        $brand = $this->brandOf($organization);

        $this->actingInOrganization($user, $organization);

        $this->assertTrue(app(BrandContext::class)->resolve()?->is($brand));
        $this->assertSame($brand->id, session(OrganizationContext::BRAND_SESSION_KEY));
    }

    // ---------------------------------------------------------------------
    // Créditos
    // ---------------------------------------------------------------------

    #[DataProvider('ownerAndMember')]
    public function test_credits_without_plan_show_no_active_plan_and_the_cta_by_role(OrganizationRole $role): void
    {
        $user = User::factory()->create();
        $organization = $this->organizationWith($user, $role, ['plan' => null]);

        $this->actingInOrganization($user, $organization);

        $sidebar = Livewire::test(Sidebar::class)
            ->assertSee('No active plan')
            ->assertDontSee('credits used')
            ->assertDontSee('Buy AI credits');

        if ($role === OrganizationRole::Owner) {
            $sidebar->assertSee('Subscribe')->assertDontSee('Ask your organization owner');
        } else {
            $sidebar->assertSee('Ask your organization owner')->assertDontSee('Subscribe');
        }
    }

    #[DataProvider('ownerAndMember')]
    public function test_credits_with_plan_and_monthly_limit_show_the_bar(OrganizationRole $role): void
    {
        PlanLimit::query()->create([
            'plan' => 'copy_briefing',
            'action' => PlanLimit::MONTHLY_CREDIT_LIMIT_ACTION,
            'credits' => 1000,
        ]);

        $user = User::factory()->create(['purchased_credits_balance' => 7]);
        $organization = $this->organizationWith($user, $role, [
            'plan' => 'copy_briefing',
            'plan_credits_used_this_month' => 120,
            'plan_credits_balance' => 880,
            'purchased_credits_balance' => 40,
        ]);

        $this->actingInOrganization($user, $organization);

        Livewire::test(Sidebar::class)
            ->assertSee('120/1000 credits used')
            ->assertSee('style="width: 12%;"', false)
            ->assertSee('Buy AI credits')
            ->assertSee('Coming soon')
            // As três carteiras, separadas — nunca a soma (CLAUDE.md regra 27).
            ->assertSee('Plan credits: 880')
            ->assertSee('Purchased credits (organization): 40')
            ->assertSee('Personal credits: 7')
            ->assertDontSee('927')
            ->assertDontSee('No active plan');
    }

    #[DataProvider('ownerAndMember')]
    public function test_credits_with_plan_and_no_monthly_limit_show_only_the_usage(OrganizationRole $role): void
    {
        $user = User::factory()->create();
        $organization = $this->organizationWith($user, $role, [
            'plan' => 'studio_visual',
            'plan_credits_used_this_month' => 120,
        ]);

        $this->actingInOrganization($user, $organization);

        Livewire::test(Sidebar::class)
            ->assertSee('120 credits used')
            ->assertDontSee('120/')
            ->assertDontSee('style="width:', false)
            ->assertSee('Buy AI credits');
    }

    public function test_profile_menu_shows_the_same_credit_usage(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@arkion.test']);
        $organization = $this->organizationWith($user, OrganizationRole::Member, ['plan' => null]);

        $this->actingInOrganization($user, $organization);

        Livewire::test(Header::class)
            ->assertSee('Ada Lovelace')
            ->assertSee('ada@arkion.test')
            ->assertSee('No active plan')
            ->assertSee('Ask your organization owner');
    }

    // ---------------------------------------------------------------------
    // Shell
    // ---------------------------------------------------------------------

    public function test_referral_is_hidden_by_default(): void
    {
        $user = User::factory()->create();
        $this->organizationWith($user);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertDontSee('referral-open-btn')
            ->assertDontSee('referral-modal')
            ->assertDontSee('Share Arkion with friends');
    }

    public function test_referral_renders_when_the_flag_is_enabled(): void
    {
        config(['arkion.features.referral' => true]);

        $user = User::factory()->create();
        $this->organizationWith($user);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('referral-open-btn')
            ->assertSee('referral-modal')
            ->assertDontSee('ARK-ORG92');
    }

    public function test_shell_renders_without_dead_links_or_mockup_sample_data(): void
    {
        $user = User::factory()->create();
        $this->organizationWith($user);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('<title>Arkion</title>', false)
            ->assertSee('Generate Article')
            ->assertSee('Generate Post')
            ->assertSee('Generate Ad')
            ->assertSee('No activity yet.')
            ->assertSee('Nothing new yet.')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Tiny AI Lab')
            ->assertDontSee('Padaria do Zé')
            ->assertDontSee('James Collison');
    }

    public function test_activity_lists_the_user_notifications(): void
    {
        $user = User::factory()->create();
        $organization = $this->organizationWith($user);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ExecutionCompleted',
            'data' => ['title' => 'Your briefing is ready', 'resource' => 'Briefings'],
        ]);

        $this->actingInOrganization($user, $organization);

        Livewire::test(Header::class)
            ->assertSee('Your briefing is ready')
            ->assertSee('Resource: Briefings')
            ->assertSee('1 NEW')
            ->assertDontSee('No activity yet.');
    }
}
