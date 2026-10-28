<?php

namespace App\Providers;

use App\Models\Execution;
use App\Models\Organization;
use App\Models\Project;
use App\Policies\ExecutionPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ProjectPolicy;
use App\Services\BrandContext;
use App\Services\CreditService;
use App\Services\ExecutionService;
use App\Services\FirebaseService;
use App\Services\N8nService;
use App\Services\OrganizationContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FirebaseService::class);

        // Stateless: apenas transporta Executions até o webhook do n8n.
        $this->app->singleton(N8nService::class);

        // Stateless: todo estado vive em plan_limits e nos saldos de users.
        $this->app->singleton(CreditService::class);

        // Stateless: orquestra CreditService, Policy e fila a cada chamada.
        $this->app->singleton(ExecutionService::class);

        // `scoped`, nunca singleton: o contexto é da requisição. Um singleton
        // sobreviveria entre requisições de usuários diferentes sob Octane ou
        // em um Worker, e a organização ativa de um vazaria para o próximo
        // (SAD 20.5 e 20.6).
        $this->app->scoped(OrganizationContext::class);

        // A Brand ativa segue a mesma regra: depende da organização ativa da
        // requisição e nunca pode sobreviver a ela (SAD 20.5).
        $this->app->scoped(BrandContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Execution::class, ExecutionPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
    }
}
