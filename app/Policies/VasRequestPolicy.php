<?php

namespace App\Policies;

use App\Services\SubmissionAccessService;
use App\Support\RbacPermission;
use App\Models\User;
use App\Models\VasRequestSubmission;

class VasRequestPolicy
{
    private static function hasBackOfficeRole(User $user): bool
    {
        return $user->hasRole('back_office')
            || $user->hasRole('backoffice')
            || $user->hasRole('back_office_executive');
    }

    public static function isValidAssignee(User $assignee): bool
    {
        return self::hasBackOfficeRole($assignee);
    }

    public function viewAny(User $user): bool
    {
        if (self::hasBackOfficeRole($user)) {
            return true;
        }

        return RbacPermission::can($user, ['vas', 'vas_requests'], 'read', [
            'vas.view',
            'vas.view.all',
            'vas_requests.list',
            'vas_requests.view',
        ]);
    }

    public function view(User $user, VasRequestSubmission $vas): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->can('vas.view.all')) {
            return true;
        }

        // Back office users can view all VAS requests.
        if (self::hasBackOfficeRole($user)) {
            return true;
        }

        return SubmissionAccessService::canAccessRecord($user, $vas, ['back_office_executive_id']);
    }

    public function create(User $user): bool
    {
        return RbacPermission::can($user, ['vas', 'vas_requests'], 'create', [
            'vas.create',
            'vas_requests.create',
        ]);
    }

    public function update(User $user, VasRequestSubmission $vas): bool
    {
        return RbacPermission::can($user, ['vas', 'vas_requests'], 'update', [
            'vas.edit',
            'vas_requests.edit',
            'vas_requests.process_vas_requests',
        ]) && $this->view($user, $vas);
    }

    public function delete(User $user, VasRequestSubmission $vas): bool
    {
        return RbacPermission::can($user, ['vas', 'vas_requests'], 'delete', [
            'vas.delete',
            'vas_requests.delete',
        ]) && $this->view($user, $vas);
    }

    public function submit(User $user, VasRequestSubmission $vas): bool
    {
        return $vas->status === 'draft'
            && (int)$vas->created_by === (int)$user->id;
    }

    public function approve(User $user): bool
    {
        return $user->can('vas.approve');
    }

    public function reject(User $user): bool
    {
        return $user->can('vas.reject');
    }

    public function assignAny(User $user): bool
    {
        if (! self::hasBackOfficeRole($user)) {
            return false;
        }

        return RbacPermission::can($user, ['vas_requests', 'back_office'], 'update', [
            'back_office.assign_bo_executive',
            'vas_requests.process_vas_requests',
        ]);
    }

    public function assign(User $user, VasRequestSubmission $vas): bool
    {
        return $this->assignAny($user) && $this->view($user, $vas);
    }
}
