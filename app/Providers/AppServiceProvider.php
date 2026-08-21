<?php

namespace App\Providers;

use App\Models\Execution;
use App\Models\Project;
use App\Policies\ExecutionPolicy;
use App\Policies\ProjectPolicy;
use App\Services\CreditService;
use App\Services\ExecutionService;
use App\Services\FirebaseService;
use App\Services\N8nService;
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

        // Stateless: todo estado vive em plan_limits e em users.credits_balance.
        $this->app->singleton(CreditService::class);

        // Stateless: orquestra CreditService, Policy e fila a cada chamada.
        $this->app->singleton(ExecutionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Execution::class, ExecutionPolicy::class);
    }
}
