<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VasRequestSubmission;

class VasRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vas.view');
    }

    public function view(User $user, VasRequestSubmission $vas): bool
    {
        if ($user->can('vas.view.all')) return true;

        return in_array($user->id, [
            $vas->created_by,
            $vas->sales_agent_id,
            $vas->team_leader_id,
            $vas->manager_id,
            $vas->back_office_executive_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $user->can('vas.create');
    }

    public function update(User $user, VasRequestSubmission $vas): bool
    {
        return $user->can('vas.edit') && $this->view($user, $vas);
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
}
