<?php

use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function disableNonEssentialApiMiddlewareForFilterPerfTests(\Tests\TestCase $testCase): void
{
    $testCase->withoutMiddleware([
        \App\Http\Middleware\CheckStatus::class,
        \App\Http\Middleware\BreadcrumbTrail::class,
        \App\Http\Middleware\AuditApiActivity::class,
        \App\Http\Middleware\EnforcePasswordExpiry::class,
        \App\Http\Middleware\ValidateSessionToken::class,
        \App\Http\Middleware\ApplySecuritySettings::class,
    ]);
}

function perfFilterUser(): User
{
    $role = Role::where('name', 'superadmin')->where('guard_name', 'web')->first()
        ?? Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'name' => 'Perf Filter User',
        'email' => 'perf_filter_user_' . uniqid() . '@test.com',
        'status' => 'active',
        'password' => bcrypt('Password1!'),
    ]);
    $user->assignRole($role);

    return $user;
}

function seedFilterFixtures(): void
{
    $now = now();
    DB::table('clients')->insert([
        [
            'company_name' => 'Filter Co 1',
            'account_number' => 'F-001',
            'status' => 'Normal',
            'service_category' => 'Retail',
            'service_type' => 'Internet',
            'product_type' => 'Fiber',
            'product_name' => 'A',
            'work_order_status' => 'open',
            'payment_connection' => 'postpaid',
            'contract_type' => 'annual',
            'clawback_chum' => 'No',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'company_name' => 'Filter Co 2',
            'account_number' => 'F-002',
            'status' => 'Churn',
            'service_category' => 'SMB',
            'service_type' => 'Voice',
            'product_type' => 'SIM',
            'product_name' => 'B',
            'work_order_status' => 'closed',
            'payment_connection' => 'prepaid',
            'contract_type' => 'monthly',
            'clawback_chum' => 'Yes',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $clientIds = DB::table('clients')->orderBy('id')->pluck('id')->all();
    if (! empty($clientIds)) {
        DB::table('client_company_details')->insert([
            'client_id' => $clientIds[0],
            'company_category' => 'Telecom',
            'account_manager_name' => 'Manager One',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('client_alerts')->insert([
            'client_id' => $clientIds[0],
            'alert_type' => 'Trade License Expiry',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

it('keeps filters endpoint query count low', function () {
    disableNonEssentialApiMiddlewareForFilterPerfTests($this);
    seedFilterFixtures();
    $user = perfFilterUser();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $start = microtime(true);
    $response = $this->actingAs($user)->getJson('/api/clients/filters');
    $elapsed = microtime(true) - $start;
    $queryCount = count(DB::getQueryLog());

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'data', 'message', 'meta']);

    expect($queryCount)->toBeLessThanOrEqual(10);
    expect($elapsed)->toBeLessThan(1.0);
});

it('busts filters cache after client update', function () {
    disableNonEssentialApiMiddlewareForFilterPerfTests($this);
    seedFilterFixtures();
    $user = perfFilterUser();

    $first = $this->actingAs($user)->getJson('/api/clients/filters');
    $first->assertStatus(200);
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();

    $client = Client::query()->firstOrFail();
    $client->status = 'Clawback';
    $client->save();

    $second = $this->actingAs($user)->getJson('/api/clients/filters', ['If-None-Match' => (string) $etag]);
    $second->assertStatus(200);
});

