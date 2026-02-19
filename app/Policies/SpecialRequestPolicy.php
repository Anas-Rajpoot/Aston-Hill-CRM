<?php

namespace App\Policies;

use App\Models\SpecialRequest;
use App\Models\User;

class SpecialRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SpecialRequest $specialRequest): bool
    {
        return true;
    }

    public function update(User $user, SpecialRequest $specialRequest): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }
        return (int) $specialRequest->created_by === (int) $user->id;
    }
}
