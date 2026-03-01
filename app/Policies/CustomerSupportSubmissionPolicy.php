<?php

namespace App\Policies;

use App\Models\CustomerSupportSubmission;
use App\Models\User;
use App\Services\SubmissionAccessService;
use App\Support\RbacPermission;

class CustomerSupportSubmissionPolicy
{
    private static function hasCsrRole(User $user): bool
    {
        return $user->hasRole('customer_support_representative')
            || $user->hasRole('support_manager')
            || $user->hasRole('csr');
    }

    public static function isValidAssignee(User $assignee): bool
    {
        return self::hasCsrRole($assignee);
    }

    public function viewAny(User $user): bool
    {
        return RbacPermission::can($user, 'customer_support_requests', 'read', [
            'customer_support_requests.list',
            'customer_support_requests.view',
        ]);
    }

    public function view(User $user, CustomerSupportSubmission $submission): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        // CSR team can view all customer support requests.
        if (self::hasCsrRole($user)) {
            return true;
        }

        return SubmissionAccessService::canAccessRecord($user, $submission, ['csr_id']);
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

    public function assignAny(User $user): bool
    {
        if (! self::hasCsrRole($user)) {
            return false;
        }

        return RbacPermission::can($user, 'customer_support_requests', 'update', [
            'customer_support_requests.assign_csr',
        ]);
    }

    public function assign(User $user, CustomerSupportSubmission $submission): bool
    {
        return $this->assignAny($user) && $this->view($user, $submission);
    }
}
