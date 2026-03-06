<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CiscoExtension;

class CiscoExtensionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->can('extensions.list');
    }

    public function view(User $user, CiscoExtension $ciscoExtension): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->can('extensions.create');
    }

    public function update(User $user, CiscoExtension $ciscoExtension): bool
    {
        return $user->hasRole('superadmin') || $user->can('extensions.edit');
    }

    public function delete(User $user, CiscoExtension $ciscoExtension): bool
    {
        return $user->hasRole('superadmin') || $user->can('extensions.delete');
    }
}
