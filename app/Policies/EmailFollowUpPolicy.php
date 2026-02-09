<?php

namespace App\Policies;

use App\Models\User;
use App\Models\EmailFollowUp;

class EmailFollowUpPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('emails_followup.list');
    }

    public function view(User $user, EmailFollowUp $emailFollowUp): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }
        return (int) $emailFollowUp->created_by === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('emails_followup.create');
    }

    public function update(User $user, EmailFollowUp $emailFollowUp): bool
    {
        return $user->can('emails_followup.edit') && $this->view($user, $emailFollowUp);
    }

    public function delete(User $user, EmailFollowUp $emailFollowUp): bool
    {
        return $user->can('emails_followup.delete') && $this->view($user, $emailFollowUp);
    }
}
