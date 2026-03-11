<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RoleUsersSeeder extends Seeder
{
    private const MIN_USERS_PER_ROLE = 3;
    private const DEFAULT_PASSWORD = 'Password@123';

    public function run(): void
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        foreach ($roles as $role) {
            $existingCount = User::query()
                ->whereHas('roles', fn ($q) => $q->where('roles.id', $role->id))
                ->count();

            if ($existingCount >= self::MIN_USERS_PER_ROLE) {
                continue;
            }

            $needed = self::MIN_USERS_PER_ROLE - $existingCount;
            $created = 0;
            $sequence = 1;
            $prefix = $this->emailPrefixForRole($role->name);

            while ($created < $needed) {
                $email = sprintf('%s.user%02d@example.com', $prefix, $sequence);
                $sequence++;

                $user = User::firstOrNew(['email' => $email]);
                $isNew = ! $user->exists;

                if ($isNew) {
                    $user->name = Str::title(str_replace('_', ' ', $role->name)).' '.($existingCount + $created + 1);
                    $user->password = Hash::make(self::DEFAULT_PASSWORD);
                    $user->status = 'approved';
                    $user->must_change_password = false;
                    $user->email_verified_at = now();
                    $user->department = str_replace('_', ' ', $role->name);
                    $user->employee_number = sprintf(
                        'R%02dU%03d',
                        (int) $role->id,
                        (int) ($existingCount + $created + 1)
                    );
                    $user->save();
                } else {
                    if ($user->status !== 'approved') {
                        $user->status = 'approved';
                        $user->save();
                    }
                }

                $user->syncRoles([$role->name]);
                $created++;
            }
        }
    }

    private function emailPrefixForRole(string $roleName): string
    {
        $prefix = preg_replace('/[^a-z0-9]+/', '', strtolower($roleName)) ?? '';
        return $prefix !== '' ? $prefix : 'roleuser';
    }
}

