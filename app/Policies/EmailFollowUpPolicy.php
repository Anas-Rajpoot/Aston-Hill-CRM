<?php

namespace App\Policies;

use App\Models\User;
use App\Models\EmailFollowUp;
use App\Support\RbacPermission;

class EmailFollowUpPolicy
{
    public function viewAny(User $user): bool
    {
        return RbacPermission::can($user, 'emails_followup', 'read', [
            'emails_followup.list',
            'emails_followup.view',
        ]);
    }

    public function view(User $user, EmailFollowUp $emailFollowUp): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return RbacPermission::can($user, 'emails_followup', 'create', [
            'emails_followup.create',
        ]);
    }

    public function update(User $user, EmailFollowUp $emailFollowUp): bool
    {
        return RbacPermission::can($user, 'emails_followup', 'update', [
            'emails_followup.edit',
        ]);
    }

    public function delete(User $user, EmailFollowUp $emailFollowUp): bool
    {
        return RbacPermission::can($user, 'emails_followup', 'delete', [
            'emails_followup.delete',
        ]);
    }
}
