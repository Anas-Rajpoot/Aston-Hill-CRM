<?php

declare(strict_types=1);

use App\Models\CustomerSupportSubmission;
use App\Models\FieldSubmission;
use App\Models\LeadSubmission;
use App\Models\User;
use App\Models\VasRequestSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$results = [];
$ok = static function (string $name, string $details = '') use (&$results): void {
    $results[] = ['status' => 'PASS', 'check' => $name, 'details' => $details];
};
$fail = static function (string $name, string $details = '') use (&$results): void {
    $results[] = ['status' => 'FAIL', 'check' => $name, 'details' => $details];
};

try {
    DB::beginTransaction();

    $superadmin = User::role('superadmin')->first();
    $manager = User::role('manager')->first();
    $teamLeader = User::role('team_leader')->first();
    $backOffice = User::role('back_office')->first();
    $fieldAgent = User::role('field_agent')->first();
    $csr = User::role('customer_support_representative')->first();

    foreach (
        [
            'superadmin' => $superadmin,
            'manager' => $manager,
            'team_leader' => $teamLeader,
            'back_office' => $backOffice,
            'field_agent' => $fieldAgent,
            'csr' => $csr,
        ] as $roleName => $user
    ) {
        if (!$user) {
            throw new RuntimeException("Missing user for role: {$roleName}");
        }
    }

    // Ensure target roles have required permissions for assignment/list tests.
    $requiredByRole = [
        'back_office' => [
            'lead-submissions.view',
            'lead-submissions.list',
            'back_office.assign_bo_executive',
            'vas_requests.list',
            'vas_requests.view',
            'vas_requests.process_vas_requests',
        ],
        'field_agent' => [
            'field-submissions.view',
            'field-submissions.list',
        ],
        'customer_support_representative' => [
            'customer_support_requests.list',
            'customer_support_requests.view',
            'customer_support_requests.assign_csr',
        ],
    ];

    foreach ($requiredByRole as $roleName => $permissions) {
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            throw new RuntimeException("Missing role: {$roleName}");
        }
        foreach ($permissions as $permName) {
            $permission = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    $now = now();
    $token = 'SMOKE-' . (string) time();

    $lead = LeadSubmission::create([
        'created_by' => $superadmin->id,
        'status' => 'submitted',
        'step' => 1,
        'company_name' => "Lead {$token}",
        'account_number' => 'AC-' . random_int(10000, 99999),
        'contact_number_gsm' => '971500000001',
        'address' => 'Smoke address',
        'emirate' => 'Dubai',
        'product' => 'Internet',
        'ae_domain' => 'smoke.ae',
        'manager_id' => $manager->id,
        'team_leader_id' => $teamLeader->id,
        'sales_agent_id' => $fieldAgent->id,
        'submitted_at' => $now,
    ]);

    $field = FieldSubmission::create([
        'created_by' => $superadmin->id,
        'status' => 'submitted',
        'company_name' => "Field {$token}",
        'contact_number' => '971500000002',
        'product' => 'Visit',
        'emirates' => 'Dubai',
        'complete_address' => 'Smoke address',
        'manager_id' => $manager->id,
        'team_leader_id' => $teamLeader->id,
        'sales_agent_id' => $fieldAgent->id,
        'submitted_at' => $now,
    ]);

    $vas = VasRequestSubmission::create([
        'created_by' => $superadmin->id,
        'status' => 'submitted',
        'request_type' => 'Other Request',
        'account_number' => 'VA-' . random_int(10000, 99999),
        'contact_number' => '971500000003',
        'company_name' => "VAS {$token}",
        'description' => 'Smoke test request',
        'manager_id' => $manager->id,
        'team_leader_id' => $teamLeader->id,
        'sales_agent_id' => $fieldAgent->id,
        'submitted_at' => $now,
    ]);

    $cs = CustomerSupportSubmission::create([
        'created_by' => $superadmin->id,
        'status' => 'submitted',
        'issue_category' => 'Other Request',
        'company_name' => "CS {$token}",
        'account_number' => 'CS-' . random_int(10000, 99999),
        'contact_number' => '971500000004',
        'issue_description' => 'Smoke support issue',
        'manager_id' => $manager->id,
        'team_leader_id' => $teamLeader->id,
        'sales_agent_id' => $fieldAgent->id,
        'submitted_at' => $now,
    ]);

    // Superadmin policy bypass checks
    Gate::forUser($superadmin)->allows('create', LeadSubmission::class) ? $ok('Superadmin can create lead') : $fail('Superadmin can create lead');
    Gate::forUser($superadmin)->allows('update', $lead) ? $ok('Superadmin can update lead') : $fail('Superadmin can update lead');
    Gate::forUser($superadmin)->allows('delete', $lead) ? $ok('Superadmin can delete lead') : $fail('Superadmin can delete lead');
    Gate::forUser($superadmin)->allows('assign', $lead) ? $ok('Superadmin can assign lead') : $fail('Superadmin can assign lead');

    // User CRUD as superadmin
    try {
        $tmpUser = User::create([
            'name' => 'Smoke User',
            'email' => 'smoke.user.' . time() . '@example.com',
            'password' => bcrypt('Test@12345'),
            'status' => 'approved',
        ]);
        $tmpUser->assignRole('field_agent');
        $tmpUser->name = 'Smoke User Updated';
        $tmpUser->save();
        $tmpUser->delete();
        $ok('User CRUD (create/update/delete) as superadmin');
    } catch (Throwable $e) {
        $fail('User CRUD (create/update/delete) as superadmin', $e->getMessage());
    }

    // Role-specific assignment flows
    if (Gate::forUser($backOffice)->allows('assign', $lead)) {
        $lead->update(['executive_id' => $backOffice->id]);
        ((int) $lead->fresh()->executive_id === (int) $backOffice->id)
            ? $ok('Back office can assign lead to back office')
            : $fail('Back office can assign lead to back office');
    } else {
        $fail('Back office assign lead policy denied');
    }

    if (Gate::forUser($backOffice)->allows('assign', $vas)) {
        $vas->update(['back_office_executive_id' => $backOffice->id]);
        ((int) $vas->fresh()->back_office_executive_id === (int) $backOffice->id)
            ? $ok('Back office can assign VAS to back office')
            : $fail('Back office can assign VAS to back office');
    } else {
        $fail('Back office assign VAS policy denied');
    }

    if (Gate::forUser($fieldAgent)->allows('assign', $field)) {
        $field->update(['field_executive_id' => $fieldAgent->id]);
        ((int) $field->fresh()->field_executive_id === (int) $fieldAgent->id)
            ? $ok('Field agent can assign field submission')
            : $fail('Field agent can assign field submission');
    } else {
        $fail('Field agent assign field submission policy denied');
    }

    if (Gate::forUser($csr)->allows('assign', $cs)) {
        $cs->update(['csr_id' => $csr->id, 'csr_name' => $csr->name]);
        ((int) $cs->fresh()->csr_id === (int) $csr->id)
            ? $ok('CSR can assign customer support request')
            : $fail('CSR can assign customer support request');
    } else {
        $fail('CSR assign customer support policy denied');
    }

    // Listing/filter checks
    LeadSubmission::query()->visibleTo($backOffice)->where('company_name', 'like', "%{$token}%")->exists()
        ? $ok('Lead list/filter works for back office')
        : $fail('Lead list/filter works for back office');

    FieldSubmission::query()->visibleTo($fieldAgent)->where('company_name', 'like', "%{$token}%")->exists()
        ? $ok('Field list/filter works for field agent')
        : $fail('Field list/filter works for field agent');

    VasRequestSubmission::query()->visibleTo($backOffice)->where('company_name', 'like', "%{$token}%")->exists()
        ? $ok('VAS list/filter works for back office')
        : $fail('VAS list/filter works for back office');

    CustomerSupportSubmission::query()->visibleTo($csr)->where('company_name', 'like', "%{$token}%")->exists()
        ? $ok('CS list/filter works for CSR')
        : $fail('CS list/filter works for CSR');

    DB::rollBack();
} catch (Throwable $e) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    $fail('Smoke execution exception', $e->getMessage());
}

$passCount = count(array_filter($results, static fn ($r) => $r['status'] === 'PASS'));
$failCount = count(array_filter($results, static fn ($r) => $r['status'] === 'FAIL'));

echo json_encode([
    'summary' => [
        'pass' => $passCount,
        'fail' => $failCount,
        'total' => count($results),
    ],
    'results' => $results,
], JSON_PRETTY_PRINT) . PHP_EOL;

