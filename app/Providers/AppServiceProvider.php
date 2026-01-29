<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Account;
use App\Policies\AccountPolicy;
use App\Policies\LeadSubmissionPolicy;
use App\Repositories\Contracts\LeadSubmissionRepositoryInterface;
use App\Repositories\Eloquent\LeadSubmissionRepository;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Account::class => AccountPolicy::class,
        LeadSubmissionPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LeadSubmissionRepositoryInterface::class, LeadSubmissionRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });
    }
}
