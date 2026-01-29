<?php

namespace App\Policies;

use App\Models\User;

class LeadSubmissionPolicy
{
    public function viewAny(User $user)
    {
        return $user->can('lead-submissions.view');
    }

    public function create(User $user)
    {
        return $user->can('lead-submissions.create');
    }

    public function view(User $user, LeadSubmission $lead)
    {
        return $user->hasRole('superadmin')
            || $user->can('lead-submissions.view_all')
            || $lead->created_by === $user->id;
    }

    public function submit(User $user, LeadSubmission $lead)
    {
        return $lead->status === 'draft'
            && $lead->created_by === $user->id;
    }
}

