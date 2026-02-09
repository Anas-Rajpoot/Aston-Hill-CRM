<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Account;
use App\Models\CustomerSupportSubmission;
use App\Models\FieldSubmission;
use App\Models\LeadSubmission;
use App\Policies\AccountPolicy;
use App\Policies\CustomerSupportSubmissionPolicy;
use App\Policies\FieldSubmissionPolicy;
use App\Policies\LeadSubmissionPolicy;
use App\Observers\LeadSubmissionObserver;
use App\Repositories\Contracts\LeadSubmissionRepositoryInterface;
use App\Repositories\Eloquent\LeadSubmissionRepository;
use App\Models\VasRequestSubmission;
use App\Policies\VasRequestPolicy;
use App\Observers\VasRequestObserver;

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
        LeadSubmission::observe(LeadSubmissionObserver::class);
        VasRequestSubmission::observe(VasRequestObserver::class);
        Gate::policy(LeadSubmission::class, LeadSubmissionPolicy::class);
        Gate::policy(FieldSubmission::class, FieldSubmissionPolicy::class);
        Gate::policy(CustomerSupportSubmission::class, CustomerSupportSubmissionPolicy::class);
        Gate::policy(VasRequestSubmission::class, VasRequestPolicy::class);
        Gate::before(function ($user, $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });
    }
}
