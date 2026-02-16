# CRM Compliance Test Suite

## Overview

This test suite validates the entire Aston Hill CRM application for:
- **Permission Matrix**: All permissions exist in DB and are properly assigned
- **Route Protection**: All API endpoints enforce authentication/authorization  
- **Audit Logging**: All mutations are tracked in audit tables
- **DataTable Functionality**: Sort, filter, column customization, pagination
- **Module CRUD**: Create, Read, Update, Delete operations for each module

## Test Files

| File | Purpose | Tests |
|------|---------|-------|
| `tests/Feature/ComplianceTest.php` | Core compliance: permissions, routes, audit, CRUD | ~80+ |
| `tests/Feature/DataTableTest.php` | DataTable endpoints: sort, filter, columns | ~50+ |
| `tests/Feature/PermissionEnforcementTest.php` | Role-based access control enforcement | ~35+ |

## Running the Tests

### Prerequisites
```bash
# Ensure database is seeded
php artisan migrate:fresh --seed

# Or seed permissions specifically
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder
```

### Run All Compliance Tests
```bash
# Run the full compliance test suite
php artisan test --filter=Compliance

# Run DataTable tests
php artisan test --filter=DataTable

# Run Permission enforcement tests
php artisan test --filter=PermissionEnforcement

# Run everything
php artisan test tests/Feature/ComplianceTest.php tests/Feature/DataTableTest.php tests/Feature/PermissionEnforcementTest.php
```

### Run with Pest (recommended)
```bash
# Verbose output with all test names
./vendor/bin/pest tests/Feature/ComplianceTest.php --verbose
./vendor/bin/pest tests/Feature/DataTableTest.php --verbose
./vendor/bin/pest tests/Feature/PermissionEnforcementTest.php --verbose

# Run all at once
./vendor/bin/pest tests/Feature/ --verbose
```

## Compliance Audit Command

A standalone Artisan command scans your project without running tests:

```bash
# Run the audit scanner (read-only)
php artisan compliance:audit

# Run with auto-fix (creates missing permissions, assigns to superadmin)
php artisan compliance:audit --fix

# JSON output only
php artisan compliance:audit --json
```

### What the Scanner Checks
1. **Route Discovery** — Scans all registered API routes, maps to modules
2. **Permission Inventory** — Cross-checks `config/permissions.php` vs DB
3. **Audit Tables** — Validates all audit tables exist with required columns
4. **DataTable Components** — Scans Vue table components for sort/filter/column features
5. **Controller Audit Coverage** — Checks each controller for audit logging calls

### Reports
Generated at `storage/app/reports/`:
- `compliance-{timestamp}.html` — Visual HTML report (open in browser)
- `compliance-{timestamp}.json` — Machine-readable JSON report

## Test Categories

### 1. Permission Inventory (ComplianceTest.php)
- All `config/permissions.php` module × action permissions exist in DB
- All structure-based granular permissions exist in DB
- All standalone permissions exist in DB
- Superadmin role has ALL permissions assigned

### 2. Route Protection (ComplianceTest.php)
- Unauthenticated users get 401/302 on all protected routes
- Non-superadmin users get 403 on super-admin-only routes
- Superadmin has full access

### 3. Audit Logging (ComplianceTest.php)
- POST requests create audit_logs entries
- PUT/PATCH requests capture new_values
- DELETE requests are logged
- `audit_logs` table has all required columns
- All 8 module-specific audit tables exist with correct schema

### 4. Module CRUD (ComplianceTest.php)
Tests for: Personal Notes, Announcements, Expenses, Users, Clients,
Lead Submissions, Field Submissions, VAS Requests, Customer Support,
Cisco Extensions, Library Documents, Employees

### 5. DataTable Endpoints (DataTableTest.php)
For each module: index, filters, columns, sort param, search, pagination

### 6. Permission Enforcement (PermissionEnforcementTest.php)
- Superadmin can access ALL 25+ endpoints
- Restricted roles blocked from super-admin routes
- Settings write operations require proper permissions
- Library access gated by view-library/manage-library permissions
- Bootstrap returns user permissions and role information

## Modules Covered

| Module | Routes | CRUD | DataTable | Audit | Permissions |
|--------|--------|------|-----------|-------|-------------|
| Users | ✅ | ✅ | ✅ | ✅ | ✅ |
| Lead Submissions | ✅ | ✅ | ✅ | ✅ | ✅ |
| Field Submissions | ✅ | ✅ | ✅ | ✅ | ✅ |
| VAS Requests | ✅ | ✅ | ✅ | ✅ | ✅ |
| Customer Support | ✅ | ✅ | ✅ | ✅ | ✅ |
| Clients | ✅ | ✅ | ✅ | ✅ | ✅ |
| Expenses | ✅ | ✅ | ✅ | ✅ | ✅ |
| Employees | ✅ | ✅ | ✅ | ✅ | ✅ |
| Cisco Extensions | ✅ | ✅ | ✅ | ✅ | ✅ |
| Announcements | ✅ | ✅ | ✅ | ✅ | ✅ |
| Library Documents | ✅ | ✅ | ✅ | ✅ | ✅ |
| Personal Notes | ✅ | ✅ | — | ✅ | ✅ |
| Email Follow-ups | ✅ | ✅ | ✅ | ✅ | ✅ |
| DSP Tracker | ✅ | ✅ | ✅ | ✅ | ✅ |
| Verifiers | ✅ | ✅ | ✅ | ✅ | ✅ |
| Attendance Log | ✅ | ✅ | ✅ | ✅ | ✅ |
| Audit Logs | ✅ | ✅ | ✅ | — | ✅ |
| Security Settings | ✅ | ✅ | — | ✅ | ✅ |
| System Preferences | ✅ | ✅ | — | ✅ | ✅ |
| SLA Rules | ✅ | ✅ | — | ✅ | ✅ |
| Notification Config | ✅ | ✅ | — | ✅ | ✅ |
| Roles & Permissions | ✅ | ✅ | — | ✅ | ✅ |

## Interpreting Results

### Pass ✅
The test assertion passed. The feature works as expected.

### Fail ❌
A critical issue was found:
- Missing permission in DB
- Route not protected
- Audit table missing columns
- Endpoint returns unexpected status code

### Warning 🟡
Non-critical issue that should be reviewed:
- Controller has mutations but relies only on global audit middleware
- DataTable component missing explicit sort/filter feature
- Permission exists but not assigned to any role
