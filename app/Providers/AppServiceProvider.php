<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentOrganization::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Platform super-admins bypass every permission/policy check.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);
    }
}
