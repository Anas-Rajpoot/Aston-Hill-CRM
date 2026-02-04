<?php

namespace App\Policies;

use App\Models\FieldSubmission;
use App\Models\User;

class FieldSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('field_head.list');
    }

    public function view(User $user, FieldSubmission $fieldSubmission): bool
    {
        return $user->can('field_head.view');
    }

    public function update(User $user, FieldSubmission $fieldSubmission): bool
    {
        return $user->can('field_head.view');
    }
}
