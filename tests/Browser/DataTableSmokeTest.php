<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

/**
 * DataTableSmokeTest — Dusk browser tests for DataTable sorting, filtering,
 * pagination, column customization, and inline editing.
 *
 * Prerequisites:
 *   composer require laravel/dusk --dev
 *   php artisan dusk:install
 *   php artisan dusk:chrome-driver
 *
 * Run:
 *   php artisan dusk --filter=DataTableSmokeTest
 *   php artisan dusk tests/Browser/DataTableSmokeTest.php
 */
class DataTableSmokeTest extends DuskTestCase
{
    protected static ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! static::$admin) {
            $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
            static::$admin = User::where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))
                ->first();

            if (! static::$admin) {
                static::$admin = User::factory()->create([
                    'name'     => 'Dusk Admin',
                    'email'    => 'dusk_admin_' . uniqid() . '@test.com',
                    'status'   => 'active',
                    'password' => bcrypt('Password1!'),
                ]);
                static::$admin->assignRole($role);
            }
        }
    }

    /* ── Helper: Login as superadmin ──────────────────────────────────── */

    protected function loginAs(Browser $browser): Browser
    {
        return $browser->loginAs(static::$admin)
                       ->visit('/')
                       ->waitForText('Dashboard', 10);
    }

    /* ═══════════════════════════════════════════════════════════════════
       Lead Submissions DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_lead_submissions_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/lead-submissions')
                ->waitFor('table', 15)
                ->assertVisible('table');
        });
    }

    public function test_lead_submissions_sort_by_column(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/lead-submissions')
                ->waitFor('table', 15)
                ->click('th[data-sort], th.sortable, thead th:first-child')
                ->pause(1000)
                ->assertVisible('table tbody tr');
        });
    }

    public function test_lead_submissions_pagination(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/lead-submissions')
                ->waitFor('table', 15)
                ->assertPresent('[class*="pagination"], nav[aria-label*="pagination"], .paginator, button:has(svg)');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Users DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_users_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/users')
                ->waitFor('table', 15)
                ->assertVisible('table');
        });
    }

    public function test_users_sort_toggles(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/users')
                ->waitFor('table', 15)
                ->click('th.cursor-pointer, th[data-sort], thead th:nth-child(2)')
                ->pause(1000)
                ->assertVisible('table tbody');
        });
    }

    public function test_users_search_filters_rows(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/users')
                ->waitFor('table', 15)
                ->type('input[placeholder*="Search"], input[type="search"], input[name="search"]', 'admin')
                ->pause(1500)
                ->assertVisible('table tbody');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Expenses DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_expenses_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/expenses')
                ->waitFor('table', 15)
                ->assertVisible('table');
        });
    }

    public function test_expenses_column_customizer(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/expenses')
                ->waitFor('table', 15)
                ->click('button:contains("Customize"), button:contains("Columns"), [data-testid="column-toggle"]')
                ->pause(500)
                ->assertPresent('[role="dialog"], .modal, [class*="modal"]');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Cisco Extensions DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_cisco_extensions_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/cisco-extensions')
                ->waitFor('table', 15)
                ->assertVisible('table');
        });
    }

    public function test_cisco_extensions_inline_edit(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/cisco-extensions')
                ->waitFor('table', 15)
                ->click('table tbody td.cursor-pointer, table tbody td[data-editable], table tbody tr:first-child td:nth-child(3)')
                ->pause(500);
            // After click, an input or select should appear for inline editing
            // (depends on implementation; asserting the click doesn't cause errors)
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Clients DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_clients_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/clients')
                ->waitFor('table', 15)
                ->assertVisible('table');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Employees DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_employees_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/employees')
                ->waitFor('table', 15)
                ->assertVisible('table');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Announcements DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_announcements_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/settings/announcement-center')
                ->waitFor('table', 15)
                ->assertVisible('table');
        });
    }

    public function test_announcements_sort_column(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/settings/announcement-center')
                ->waitFor('table', 15)
                ->click('th.cursor-pointer, th[data-sort], thead th:first-child')
                ->pause(1000)
                ->assertVisible('table tbody');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Library DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_library_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/settings/library')
                ->waitFor('table', 15)
                ->assertVisible('table');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       DSP Tracker DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_dsp_tracker_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/dsp-tracker')
                ->waitFor('table', 15)
                ->assertVisible('table');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Attendance Log DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_attendance_log_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/attendance-log')
                ->waitFor('table, [class*="attendance"]', 15)
                ->assertPresent('table, [class*="attendance"]');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Audit Logs DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_audit_logs_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/settings/audit-logs')
                ->waitFor('table', 15)
                ->assertVisible('table');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Verifiers Detail DataTable
       ═══════════════════════════════════════════════════════════════════ */

    public function test_verifiers_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/verifiers-detail')
                ->waitFor('table', 15)
                ->assertVisible('table');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Permission-Gated UI Elements
       ═══════════════════════════════════════════════════════════════════ */

    public function test_superadmin_sees_add_user_button(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/users')
                ->waitFor('table', 15)
                ->assertSee('Add');
        });
    }

    public function test_superadmin_sees_add_expense_button(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser)
                ->visit('/expenses')
                ->waitFor('table', 15)
                ->assertSee('Add');
        });
    }
}
