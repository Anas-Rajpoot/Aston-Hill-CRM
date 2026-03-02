<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = [
    ['email' => 'qa.superadmin@example.com', 'name' => 'QA Super Admin', 'role' => 'superadmin'],
    ['email' => 'qa.backoffice@example.com', 'name' => 'QA Back Office', 'role' => 'back_office'],
    ['email' => 'qa.fieldagent@example.com', 'name' => 'QA Field Agent', 'role' => 'field_agent'],
    ['email' => 'qa.csr@example.com', 'name' => 'QA CSR', 'role' => 'csr'],
];

foreach ($users as $u) {
    $role = Spatie\Permission\Models\Role::firstOrCreate([
        'name' => $u['role'],
        'guard_name' => 'web',
    ]);

    $user = App\Models\User::firstOrCreate(
        ['email' => $u['email']],
        [
            'name' => $u['name'],
            'password' => bcrypt('Password1!'),
            'status' => 'approved',
        ]
    );

    if (! $user->hasRole($u['role'])) {
        $user->assignRole($role);
    }

    $user->status = 'approved';
    $user->save();
}

echo "created\n";
