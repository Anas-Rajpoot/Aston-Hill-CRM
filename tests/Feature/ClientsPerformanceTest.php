<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function disableNonEssentialApiMiddlewareForPerfTests(\Tests\TestCase $testCase): void
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

function perfUser(): User
{
    $role = Role::where('name', 'superadmin')->where('guard_name', 'web')->first()
        ?? Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'name' => 'Perf User',
        'email' => 'perf_user_' . uniqid() . '@test.com',
        'status' => 'active',
        'password' => bcrypt('Password1!'),
    ]);
    $user->assignRole($role);

    return $user;
}

function seedClientsForPerf(int $count = 30): void
{
    $now = now();
    $rows = [];
    for ($i = 1; $i <= $count; $i++) {
        $rows[] = [
            'company_name' => 'Perf Co ' . $i,
            'account_number' => 'AC' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
            'submitted_at' => $now,
            'status' => $i % 2 === 0 ? 'Normal' : 'Churn',
            'service_category' => 'Retail',
            'service_type' => 'Internet',
            'product_type' => 'Fiber',
            'product_name' => 'Plan ' . $i,
            'work_order_status' => 'open',
            'payment_connection' => 'postpaid',
            'contract_type' => 'annual',
            'mrc' => '100',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    DB::table('clients')->insert($rows);
}

it('keeps clients endpoint efficient and slim', function () {
    disableNonEssentialApiMiddlewareForPerfTests($this);
    seedClientsForPerf(40);
    $user = perfUser();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $start = microtime(true);
    $response = $this->actingAs($user)->getJson('/api/clients?per_page=20&sort=submitted_at&order=desc');
    $elapsed = microtime(true) - $start;
    $queryCount = count(DB::getQueryLog());
    $payloadBytes = strlen((string) $response->getContent());

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data',
            'message',
            'meta' => ['per_page', 'next_cursor', 'prev_cursor', 'has_more'],
        ]);

    expect($queryCount)->toBeLessThanOrEqual(10);
    expect($payloadBytes)->toBeLessThan(50 * 1024);
    expect($elapsed)->toBeLessThan(1.0);
});

it('returns 304 when etag matches on clients endpoint', function () {
    disableNonEssentialApiMiddlewareForPerfTests($this);
    seedClientsForPerf(10);
    $user = perfUser();

    $first = $this->actingAs($user)->getJson('/api/clients?per_page=20');
    $first->assertStatus(200);
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();

    $second = $this->actingAs($user)->getJson('/api/clients?per_page=20', ['If-None-Match' => (string) $etag]);
    $second->assertStatus(304);
});

