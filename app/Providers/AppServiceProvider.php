<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Account;
use App\Models\Client;
use App\Models\CustomerSupportSubmission;
use App\Models\FieldSubmission;
use App\Models\LeadSubmission;
use App\Models\VasRequestSubmission;
use App\Models\EmailFollowUp;
use App\Models\User;
use App\Policies\AccountPolicy;
use App\Policies\ClientPolicy;
use App\Policies\CustomerSupportSubmissionPolicy;
use App\Policies\FieldSubmissionPolicy;
use App\Policies\LeadSubmissionPolicy;
use App\Policies\VasRequestPolicy;
use App\Policies\EmailFollowUpPolicy;
use App\Observers\ClientObserver;
use App\Observers\FieldSubmissionObserver;
use App\Observers\LeadSubmissionObserver;
use App\Observers\VasRequestSubmissionObserver;
use App\Observers\CustomerSupportSubmissionObserver;
use App\Observers\SubmissionCacheObserver;
use App\Observers\UserObserver;
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
        LeadSubmission::observe(LeadSubmissionObserver::class);
        FieldSubmission::observe(FieldSubmissionObserver::class);
        VasRequestSubmission::observe(VasRequestSubmissionObserver::class);
        CustomerSupportSubmission::observe(CustomerSupportSubmissionObserver::class);
        Client::observe(ClientObserver::class);
        User::observe(UserObserver::class);

        // Cache invalidation observers (flush tag-based cache on model events)
        LeadSubmission::observe(SubmissionCacheObserver::class);
        FieldSubmission::observe(SubmissionCacheObserver::class);
        CustomerSupportSubmission::observe(SubmissionCacheObserver::class);
        VasRequestSubmission::observe(SubmissionCacheObserver::class);
        Gate::policy(LeadSubmission::class, LeadSubmissionPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(FieldSubmission::class, FieldSubmissionPolicy::class);
        Gate::policy(CustomerSupportSubmission::class, CustomerSupportSubmissionPolicy::class);
        Gate::policy(VasRequestSubmission::class, VasRequestPolicy::class);
        Gate::policy(EmailFollowUp::class, EmailFollowUpPolicy::class);
        Gate::before(function ($user, $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });
    }
}
