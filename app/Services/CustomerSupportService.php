<?php

namespace App\Services;

use App\Models\CustomerSupportSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerSupportService
{
    public function create(array $data, int $userId): CustomerSupportSubmission
    {
        return DB::transaction(function () use ($data, $userId) {
            return CustomerSupportSubmission::create([
                ...$data,
                'created_by' => $userId,
                'status' => 'draft',
            ]);
        });
    }

    public function submit(CustomerSupportSubmission $submission): CustomerSupportSubmission
    {
        $submission->submit();
        $fresh = $submission->fresh();

        NotificationService::dispatchOnce('new_submission_created', 'customer_support:' . $fresh->id, [
            'module' => 'Customer Support',
            'title' => 'New Customer Request',
            'message' => sprintf('Customer support request #%d was created for %s.', $fresh->id, $fresh->company_name ?: 'N/A'),
            'url' => '/customer-support',
            'users' => User::query()->get(['id', 'name', 'email']),
        ], 900);

        return $fresh;
    }
}
