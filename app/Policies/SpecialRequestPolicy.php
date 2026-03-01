<?php

namespace App\Policies;

use App\Models\SpecialRequest;
use App\Models\User;
use App\Support\RbacPermission;

class SpecialRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return RbacPermission::can($user, 'special_requests', 'read', [
            'special_requests.list',
            'special_requests.view',
        ]);
    }

    public function view(User $user, SpecialRequest $specialRequest): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SpecialRequest $specialRequest): bool
    {
        return RbacPermission::can($user, 'special_requests', 'update', [
            'special_requests.edit',
        ]) && $this->view($user, $specialRequest);
    }

    public function create(User $user): bool
    {
        return RbacPermission::can($user, 'special_requests', 'create', [
            'special_requests.create',
        ]);
    }

    public function delete(User $user, SpecialRequest $specialRequest): bool
    {
        return RbacPermission::can($user, 'special_requests', 'delete', [
            'special_requests.delete',
        ]) && $this->view($user, $specialRequest);
    }
}
