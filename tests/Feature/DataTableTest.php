<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| DataTable Feature Tests
|--------------------------------------------------------------------------
| Validates all datatable endpoints: index, filters, columns, sort, search
*/

function dtUser(): User
{
    $role = Role::where('name', 'superadmin')->where('guard_name', 'web')->first()
        ?? Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'name' => 'DT Test User',
        'email' => 'dt_test_' . uniqid() . '@test.com',
        'status' => 'active',
        'password' => bcrypt('Password1!'),
    ]);
    $user->assignRole($role);

    return $user;
}

// ═══════════════════════════════════════════════════════════════════════════
// MODULE LISTING ENDPOINTS WITH SORT + SEARCH + PAGINATION
// ═══════════════════════════════════════════════════════════════════════════

describe('Lead Submissions DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/lead-submissions?per_page=5');
        expect($r->status())->toBe(200);
        $r->assertJsonStructure(['data', 'meta']);
    });

    it('supports sort param', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/lead-submissions?sort=submitted_at&order=desc');
        expect($r->status())->toBe(200);
    });

    it('returns filter options', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/lead-submissions/filters');
        expect($r->status())->toBe(200);
    });

    it('returns column config', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/lead-submissions/columns');
        expect($r->status())->toBe(200);
    });

    it('saves column preferences', function () {
        $r = $this->actingAs(dtUser())->postJson('/api/lead-submissions/columns', [
            'visible' => ['id', 'company_name', 'status'],
        ]);
        expect($r->status())->toBeIn([200, 201]);
    });
});

describe('Field Submissions DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/field-submissions?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('supports sort param', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/field-submissions?sort=submitted_at&order=desc');
        expect($r->status())->toBe(200);
    });

    it('returns filter options', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/field-submissions/filters');
        expect($r->status())->toBe(200);
    });
});

describe('Customer Support DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/customer-support?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('supports sort param', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/customer-support?sort=submitted_at&order=desc');
        expect($r->status())->toBe(200);
    });

    it('returns filter options', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/customer-support/filters');
        expect($r->status())->toBe(200);
    });
});

describe('VAS Requests DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/vas-requests?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('supports sort param', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/vas-requests?sort=submitted_at&order=desc');
        expect($r->status())->toBe(200);
    });

    it('returns filter options', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/vas-requests/filters');
        expect($r->status())->toBe(200);
    });
});

describe('Clients DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/clients?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('supports search by company_name', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/clients?company_name=test');
        expect($r->status())->toBe(200);
    });

    it('returns filter options', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/clients/filters');
        expect($r->status())->toBe(200);
    });
});

describe('Employees DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/employees?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('supports sort param', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/employees?sort=name&order=asc');
        expect($r->status())->toBe(200);
    });

    it('returns filter options', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/employees/filters');
        expect($r->status())->toBe(200);
    });
});

describe('Cisco Extensions DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/cisco-extensions?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('supports sort param', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/cisco-extensions?sort=extension&order=asc');
        expect($r->status())->toBe(200);
    });

    it('returns filter options', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/cisco-extensions/filters');
        expect($r->status())->toBe(200);
    });
});

describe('Expenses DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/expenses?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('supports sort param', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/expenses?sort=expense_date&order=desc');
        expect($r->status())->toBe(200);
    });

    it('returns filter options', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/expenses/filters');
        expect($r->status())->toBe(200);
    });
});

describe('Email Follow-Ups DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/email-follow-ups?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('returns filter options', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/email-follow-ups/filters');
        expect($r->status())->toBe(200);
    });
});

describe('Announcements DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/announcements?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('supports sort param', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/announcements?sort=title:asc');
        expect($r->status())->toBe(200);
    });
});

describe('Library Documents DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/library/documents?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('supports sort param', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/library/documents?sort=name:asc');
        expect($r->status())->toBe(200);
    });

    it('returns meta (categories, modules, file_types, roles)', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/library/documents/meta');
        expect($r->status())->toBe(200);
    });
});

describe('Audit Logs DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/audit-logs?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('returns meta (actions, modules, roles)', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/audit-logs/meta');
        expect($r->status())->toBe(200);
    });

    it('returns stats', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/audit-logs/stats');
        expect($r->status())->toBe(200);
    });
});

describe('DSP Tracker DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/dsp-tracker?per_page=5');
        expect($r->status())->toBe(200);
    });
});

describe('Attendance Log DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/attendance-log?per_page=5');
        expect($r->status())->toBe(200);
    });

    it('returns summary', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/attendance-log/summary');
        expect($r->status())->toBe(200);
    });
});

describe('Verifiers DataTable', function () {
    it('returns paginated data', function () {
        $r = $this->actingAs(dtUser())->getJson('/api/verifiers?per_page=5');
        expect($r->status())->toBe(200);
    });
});
