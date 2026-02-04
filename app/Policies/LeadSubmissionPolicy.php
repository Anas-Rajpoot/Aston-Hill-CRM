<?php

namespace App\Policies;

use App\Models\LeadSubmission;
use App\Models\User;

class LeadSubmissionPolicy
{
    /** Requires lead.view and at least one scoped view permission. */
    public function viewAny(User $user): bool
    {
        return $user->can('lead.view')
            && ($user->can('lead.view.all')
                || $user->can('lead.view.assigned')
                || $user->can('lead.view.created'));
    }

    public function create(User $user): bool
    {
        return $user->can('lead.create');
    }

    public function view(User $user, LeadSubmission $lead): bool
    {
        if (!$user->can('lead.view')) {
            return false;
        }
        if ($user->can('lead.view.all')) {
            return true;
        }
        if ($user->can('lead.view.created') && (int) $lead->created_by === (int) $user->id) {
            return true;
        }
        if ($user->can('lead.view.assigned')) {
            $userId = (int) $user->id;
            return (int) $lead->sales_agent_id === $userId
                || (int) $lead->team_leader_id === $userId
                || (int) $lead->manager_id === $userId;
        }
        return false;
    }

    public function update(User $user, LeadSubmission $lead): bool
    {
        return $user->can('lead.edit') && $this->view($user, $lead);
    }

    public function delete(User $user, LeadSubmission $lead): bool
    {
        return $user->can('lead.delete') && $this->view($user, $lead);
    }

    public function submit(User $user, LeadSubmission $lead): bool
    {
        return $lead->status === 'draft' && (int) $lead->created_by === (int) $user->id;
    }
}
