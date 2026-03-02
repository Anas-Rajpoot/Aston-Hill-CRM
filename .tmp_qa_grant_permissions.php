<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$map = [
    'qa.backoffice@example.com' => [
        'back_office.assign_bo_executive',
        'vas_requests.process_vas_requests',
        'lead-submissions.list',
        'lead-submissions.view',
        'vas_requests.list',
        'vas_requests.view',
    ],
    'qa.fieldagent@example.com' => [
        'field-submissions.list',
        'field-submissions.view',
    ],
    'qa.csr@example.com' => [
        'customer_support_requests.assign_csr',
        'customer_support_requests.list',
        'customer_support_requests.view',
    ],
];

foreach ($map as $email => $perms) {
    $user = App\Models\User::where('email', $email)->first();
    if (! $user) {
        continue;
    }
    $user->status = 'approved';
    $user->save();
    foreach ($perms as $permName) {
        $perm = Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => $permName,
            'guard_name' => 'web',
        ]);
        if (! $user->hasPermissionTo($perm->name)) {
            $user->givePermissionTo($perm);
        }
    }
}

echo "granted\n";
