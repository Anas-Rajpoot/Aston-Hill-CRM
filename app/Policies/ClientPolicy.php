<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Services\SubmissionAccessService;
use App\Support\RbacPermission;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return RbacPermission::can($user, ['clients', 'accounts'], 'read', [
            'clients.list',
            'accounts.list',
            'accounts.view',
        ]);
    }

    public function view(User $user, Client $client): bool
    {
        return $this->viewAny($user)
            && SubmissionAccessService::canAccessRecord($user, $client, ['account_manager_id']);
    }

    public function create(User $user): bool
    {
        return RbacPermission::can($user, ['clients', 'accounts'], 'create', [
            'clients.create',
            'accounts.create',
        ]);
    }

    public function update(User $user, Client $client): bool
    {
        return RbacPermission::can($user, ['clients', 'accounts'], 'update', [
            'clients.edit',
            'accounts.edit',
        ]) && $this->view($user, $client);
    }
}
