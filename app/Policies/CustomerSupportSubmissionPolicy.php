<?php

namespace App\Policies;

use App\Models\CustomerSupportSubmission;
use App\Models\User;
use App\Services\SubmissionAccessService;
use App\Support\RbacPermission;

class CustomerSupportSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return RbacPermission::can($user, 'customer_support_requests', 'read', [
            'customer_support_requests.list',
            'customer_support_requests.view',
        ]);
    }

    public function view(User $user, CustomerSupportSubmission $submission): bool
    {
        return $this->viewAny($user)
            && SubmissionAccessService::canAccessRecord($user, $submission, ['csr_id']);
    }

    public function create(User $user): bool
    {
        return RbacPermission::can($user, 'customer_support_requests', 'create', [
            'customer_support_requests.create',
        ]);
    }

    public function update(User $user, CustomerSupportSubmission $submission): bool
    {
        return RbacPermission::can($user, 'customer_support_requests', 'update', [
            'customer_support_requests.change_ticket_status',
            'customer_support_requests.add_resolution_remarks',
            'customer_support_requests.edit',
        ]) && $this->view($user, $submission);
    }

    public function delete(User $user, CustomerSupportSubmission $submission): bool
    {
        return RbacPermission::can($user, 'customer_support_requests', 'delete', [
            'customer_support_requests.delete',
        ]) && $this->view($user, $submission);
    }
}
