<?php

namespace App\Observers;

use App\Models\CustomerSupportSubmission;
use App\Models\FieldSubmission;
use App\Models\LeadSubmission;
use App\Models\VasRequestSubmission;
use App\Services\SubmissionCacheService;
use Illuminate\Database\Eloquent\Model;

/**
 * Flushes the module-specific cache tag whenever a submission
 * is created, updated, or deleted.
 *
 * The module key is resolved from the model class so the observer
 * can be registered without constructor parameters (Laravel resolves
 * observers from the container, which cannot inject arbitrary strings).
 */
class SubmissionCacheObserver
{
    private const MODEL_MODULE_MAP = [
        LeadSubmission::class => 'leads',
        FieldSubmission::class => 'field',
        CustomerSupportSubmission::class => 'customer-support',
        VasRequestSubmission::class => 'vas',
    ];

    public function saved(Model $model): void
    {
        $module = self::MODEL_MODULE_MAP[get_class($model)] ?? null;
        if ($module) {
            SubmissionCacheService::flush($module);
        }
    }

    public function deleted(Model $model): void
    {
        $module = self::MODEL_MODULE_MAP[get_class($model)] ?? null;
        if ($module) {
            SubmissionCacheService::flush($module);
        }
    }
}
