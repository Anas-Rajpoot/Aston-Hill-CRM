<?php

namespace App\Policies;

use App\Models\CustomerSupportSubmission;
use App\Models\User;

class CustomerSupportSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CustomerSupportSubmission $submission): bool
    {
        return true;
    }

    public function update(User $user, CustomerSupportSubmission $submission): bool
    {
        return $user->hasRole('superadmin') || $user->hasRole('back_office') || $user->hasRole('backoffice');
    }
}
