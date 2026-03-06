# Database & Model Audit Report

**Project:** Aston Hill CRM  
**Date:** Auto-generated  
**Scope:** 64 Eloquent models, 120+ migrations, 17 seeders, 1 factory  
**Type:** READ-ONLY audit — no files were modified

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Critical Issues](#2-critical-issues)
3. [Complete Table / Column Inventory](#3-complete-table--column-inventory)
4. [Missing Indexes Analysis](#4-missing-indexes-analysis)
5. [Foreign Key Gaps](#5-foreign-key-gaps)
6. [Cascade Rule Issues](#6-cascade-rule-issues)
7. [Mass Assignment Risks](#7-mass-assignment-risks)
8. [Relationship Integrity Issues](#8-relationship-integrity-issues)
9. [Schema / Migration Issues](#9-schema--migration-issues)
10. [Model Code Issues](#10-model-code-issues)
11. [Seeder & Factory Issues](#11-seeder--factory-issues)
12. [Redundant / Duplicate Data](#12-redundant--duplicate-data)
13. [Missing Soft Deletes](#13-missing-soft-deletes)
14. [Schema Improvement Recommendations](#14-schema-improvement-recommendations)
15. [Appendix A — Full Table Inventory](#appendix-a--full-table-inventory)

---

## 1. Executive Summary

| Metric | Count |
|--------|-------|
| Eloquent models | 64 |
| Migration files | 120+ |
| Database tables | ~50 |
| Seeders | 17 (9 called from DatabaseSeeder) |
| Factories | 1 (UserFactory) |
| **Critical issues** | **5** |
| **High-severity issues** | **12** |
| **Medium-severity issues** | **18** |
| **Low / informational** | **15+** |

The schema is functionally complete for the CRM's current feature set, but contains several critical model–migration mismatches, dangerous cascade rules, and missing relationships that could cause data loss or runtime errors.

---

## 2. Critical Issues

### CRIT-1: `accounts` table has no columns beyond `id` + `timestamps`

**Files:** `app/Models/Account.php`, `2026_01_20_100010_create_accounts_table.php`

The migration creates:
```php
Schema::create('accounts', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
});
```

But the model declares:
```php
protected $fillable = ['company_name', 'account_number', 'assigned_csr_id'];
```

**Impact:** Any attempt to create/update an Account will silently discard the data (if `strict` mode is off) or throw a query exception. The model is effectively non-functional against the actual schema.

**Recommendation:** Add the missing columns via a new migration, or remove the model if accounts are tracked elsewhere (the `clients` table appears to hold account data).

---

### CRIT-2: `User::leadColumnPreference()` references non-existent model

**File:** `app/Models/User.php`

```php
public function leadColumnPreference()
{
    return $this->hasOne(LeadColumnPreference::class);
}
```

The class `LeadColumnPreference` does not exist. The actual model is `LeadSubmissionColumnPreference`.

**Impact:** Any call to `$user->leadColumnPreference` will throw a `Class not found` fatal error.

**Recommendation:** Change to `$this->hasOne(LeadSubmissionColumnPreference::class)`.

---

### CRIT-3: Dangerous `cascadeOnDelete` on `special_requests.manager_id`

**File:** `2026_02_25_create_special_requests_table.php`

```php
$table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
```

**Impact:** Deleting a manager user **permanently destroys all their special requests**. This is almost certainly unintended — the requests belong to the business, not the manager.

**Recommendation:** Change to `->nullOnDelete()` and make the column nullable.

---

### CRIT-4: `library_documents.uploaded_by` FK has no nullOnDelete

**Files:** `2026_02_22_*_create_library_documents_table.php`, `2026_02_22_*_create_library_document_versions_table.php`

```php
$table->foreignId('uploaded_by')->constrained('users');
```

Standard `constrained()` defaults to `RESTRICT` on delete. Deleting a user who uploaded any library document will **fail with an FK constraint violation**.

**Impact:** Users cannot be deleted if they ever uploaded a document.

**Recommendation:** Add `->nullOnDelete()` and make the column nullable, or use a soft-delete strategy for users.

---

### CRIT-5: `EscalationLevel::resolveByRole()` queries `deleted_at` on users table, but User has no SoftDeletes

**File:** `app/Models/EscalationLevel.php`

```php
->whereNull('deleted_at')
```

The `users` table has no `deleted_at` column and the `User` model does not use `SoftDeletes`.

**Impact:** The query will throw `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'deleted_at'`.

**Recommendation:** Remove the `whereNull('deleted_at')` clause or add `SoftDeletes` to User (see Section 13).

---

## 3. Complete Table / Column Inventory

> Tables listed alphabetically. Column types are from migrations. Amendments from later migrations are merged.

| # | Table | Columns (excluding id/timestamps unless notable) | Row-Level Notes |
|---|-------|--------------------------------------------------|-----------------|
| 1 | `accounts` | *(empty — only id + timestamps)* | **CRIT-1** |
| 2 | `announcement_acknowledgements` | announcement_id (FK), user_id (FK), acknowledged_at | unique(announcement_id, user_id), timestamps=false |
| 3 | `announcements` | created_by (FK), title, type, body, link_url, link_label, priority, all_users, audiences (JSON), channels (JSON), attachment_path/name/mime/size, is_pinned, is_active, require_ack, ack_due_at, published_at, expire_at, archived_at, updated_by | `updated_by` added later, no FK constraint |
| 4 | `audit_logs` | occurred_at, user_id, user_name, user_role, action, module, record_id, record_ref, ip, device, user_agent, session_id, method, route, latency_ms, old_values (JSON), new_values (JSON), result | Many indexes. `user_id` added later with FK nullable |
| 5 | `cisco_extension_audits` | cisco_extension_id (FK dropped), user_id (FK null), action, old_values (JSON), new_values (JSON) | FK to cisco_extensions intentionally dropped for keep-on-delete |
| 6 | `cisco_extensions` | extension, landline_number, gateway, username, password, status, assigned_to (FK null), team_leader_id (FK null), manager_id (FK null), comment | |
| 7 | `client_addresses` | client_id (FK cascade), address_type, address_line, city, state, country, sort_order | |
| 8 | `client_alerts` | client_id (FK cascade), alert_type, message, expiry_date, manager_id (FK null), status, resolved, created_by (FK null) | |
| 9 | `client_audits` | client_id (FK cascade), field_name, old_value, new_value, changed_at, changed_by (FK null), ip_address, user_agent | |
| 10 | `client_company_details` | client_id (FK cascade, unique), trade_license_number, trade_license_expiry, establishment_card_number, establishment_card_expiry, account_mapping_number, account_mapping_expiry, csr_name_1, csr_name_2, csr_name_3, bills | Duplicate CSR names — see Section 12 |
| 11 | `client_contacts` | client_id (FK cascade), contact_name, designation, email, phone, is_primary, sort_order | |
| 12 | `client_csrs` | client_id (FK cascade), user_id (FK cascade), sort_order | Pivot table for CSR assignments |
| 13 | `clients` | company_name, account_number (unique), submitted_at, submission_type, service_category, status, many business fields…, manager_id/team_leader_id/sales_agent_id/account_manager_id (FK null), created_by (FK null), csr_name_1/2/3, revenue | Duplicate CSR names — see Section 12 |
| 14 | `countries` | name, code (unique, 2-char), timezone, is_active | |
| 15 | `customer_support_submission_audits` | customer_support_submission_id (FK cascade), field_name, old_value, new_value, changed_at, changed_by (FK null), ip_address, user_agent | |
| 16 | `customer_support_submissions` | created_by (FK cascade), client_id (FK null, added), issue_category, company_name, account_number (nullable), contact_number, issue_description, attachments (JSON), manager_id/team_leader_id/sales_agent_id (FK, made nullable), team_id (FK null), csr_id (FK null), status (varchar), submitted_at, ticket_number, csr_name, workflow_status, completion_date, trouble_ticket, activity, pending, resolution_remarks, internal_remarks | Duplicate csr_name + csr_id — Section 12 |
| 17 | `dropdown_options` | group, value, label, sort_order, is_active | unique(group, value) |
| 18 | `dsp_tracker_entries` | import_batch_id, activity_number, account_name, account_number, appointment_date (varchar!), appointment_time (varchar!), agent_name, product_description, remark, status, uploaded_at (varchar!), user_id (FK null) | **String dates** — Section 9 |
| 19 | `email_follow_ups` | created_by (FK cascade), email_date, notified_at, subject, category, request_from, sent_to, comment, status | |
| 20 | `email_templates` | trigger_key, name, subject, body, available_variables (JSON), updated_by (FK null) | No FK to notification_triggers |
| 21 | `escalation_levels` | level (unique), recipient_type (varchar), custom_email, is_active | |
| 22 | `escalation_logs` | module_key, record_id, escalation_level, sent_to, recipient_type, status (enum), error, sent_at | unique(module_key, record_id, escalation_level), timestamps=false |
| 23 | `expense_attachments` | expense_id (FK cascade), original_name, path, disk, mime_type, size, type | |
| 24 | `expense_audits` | expense_id (FK cascade), user_id (FK null), action, old_values (JSON), new_values (JSON) | |
| 25 | `expenses` | user_id (FK cascade), expense_date, product_category, invoice_number, product_description, comment, vat_amount, amount_without_vat, full_amount, status | |
| 26 | `field_submission_audits` | field_submission_id (FK cascade), field_name, old_value, new_value, changed_at, changed_by (FK null), ip_address, user_agent | |
| 27 | `field_submission_documents` | field_submission_id (FK cascade), doc_key, file_path, file_name, label, mime, size | |
| 28 | `field_submissions` | created_by (FK cascade), account_number, company_name, authorized_signatory_name, contact_number, product, alternate_number (nullable), emirates, location_coordinates, complete_address, additional_notes, special_instruction, manager_id (FK), team_leader_id (FK, nullable), sales_agent_id (FK, nullable), field_executive_id (FK null), team_id (FK null), client_id (FK null, added), meeting_date (datetime), field_status, remarks_by_field_agent, status (enum), submitted_at | |
| 29 | `form_drafts` | user_id (FK cascade), module, record_ref, data (JSON) | unique(user_id, module, record_ref) |
| 30 | `lead_submission_audits` | lead_submission_id (FK cascade), field_name, old_value, new_value, changed_at, changed_by (FK null), ip_address, user_agent | |
| 31 | `lead_submission_column_preferences` | user_id (FK cascade, unique), visible_columns (JSON) | |
| 32 | `lead_submission_documents` | lead_submission_id (FK cascade), service_type_id (FK null), doc_key, label, file_path, file_name, mime, size, uploaded_by (FK null) | unique(lead_submission_id, doc_key) was added then dropped then re-added |
| 33 | `lead_submissions` | created_by (FK cascade), updated_by (FK null), step, status (extended enum→varchar), status_changed_at, submission_type, account_number, company_name, many form fields…, service_category_id/service_type_id (FK null), sales_agent_id/team_leader_id/manager_id (FK null), executive_id (FK null), team_id (FK null), client_id (FK null, added), payload (JSON), submitted_at, approved_at/by, rejected_at/by, back-office fields | `approved_by` / `rejected_by` have **no FK** |
| 34 | `library_categories` | name, slug (unique), parent_id (FK self null) | |
| 35 | `library_document_versions` | document_id (FK cascade), version, storage_disk, storage_path, file_name, mime_type, size_bytes, uploaded_by (FK → users, **no nullOnDelete**), created_at | |
| 36 | `library_documents` | document_code (unique), name, description, category_id (FK null), module_keys (JSON), tags (JSON), visibility, allowed_roles (JSON), file_type, mime_type, storage_disk, storage_path, file_name, original_name, size_bytes, version_count, status, uploaded_by (FK → users, **no nullOnDelete**), updated_by (FK null) | **CRIT-4** |
| 37 | `notification_escalations` | level (unique), to_emails (JSON), enabled, updated_by (FK null) | Singleton-ish |
| 38 | `notification_logs` | trigger_key, channel, module, sent_to, status, error, payload (JSON) | |
| 39 | `notification_settings` | default_sender_email, cc_emails (JSON), bcc_emails (JSON), enable_email, enable_web, enable_sms, enable_sla_alerts, updated_by (FK null) | Singleton |
| 40 | `notification_triggers` | key (unique), name, module, website_enabled, email_enabled, in_app_enabled, email_alert_enabled, is_active, updated_by (FK null) | |
| 41 | `otps` | user_id (FK cascade), otp, expires_at | |
| 42 | `password_reset_tokens` | email (PK), token, created_at | Laravel default |
| 43 | `personal_access_tokens` | tokenable (morph), name, token, abilities, last_used_at, expires_at | Sanctum |
| 44 | `personal_notes` | user_id (FK cascade), title, body, status (enum), priority (enum), due_date, completed_at | |
| 45 | `permissions` | name, guard_name | Spatie |
| 46 | `model_has_permissions` | permission_id, model_type, model_id | Spatie |
| 47 | `model_has_roles` | role_id, model_type, model_id | Spatie |
| 48 | `role_has_permissions` | permission_id, role_id | Spatie |
| 49 | `role_inheritance` | parent_role_id (FK cascade), child_role_id (FK cascade) | unique(parent, child), check != self |
| 50 | `roles` | name, guard_name, status (default 'active') | Spatie extended |
| 51 | `security_settings` | session_lifetime_minutes, max_concurrent_sessions, enforce_single_session, login_max_attempts, login_lockout_duration_minutes, password_min_length, password_require_uppercase, password_require_number, password_require_symbol, password_expiry_days, updated_by (FK null) | Singleton |
| 52 | `service_categories` | name, slug (unique), is_active, sort_order | Model has `description` in fillable but column missing — Section 9 |
| 53 | `service_types` | service_category_id (FK cascade), name, slug (unique), is_active, sort_order, schema (JSON) | |
| 54 | `sessions` | id (string PK), user_id, ip_address, user_agent, payload, last_activity | Laravel session driver |
| 55 | `sla_rules` | module_key (unique), module_name, sla_duration_minutes, warning_threshold_minutes, notification_email, is_active, updated_by (FK null) | |
| 56 | `special_request_audits` | special_request_id (FK cascade), field_name, old_value, new_value, changed_at, changed_by (FK null), ip_address, user_agent | |
| 57 | `special_request_documents` | special_request_id (FK cascade), doc_key, label, file_path, file_name, mime, size | |
| 58 | `special_requests` | created_by (FK cascade), company_name, account_number, request_type, status, complete_address, special_instruction, manager_id (FK **cascade!**), team_leader_id (FK, made nullable), sales_agent_id (FK, made nullable), submitted_at | **CRIT-3** |
| 59 | `system_audit_logs` | user_id (FK null), event, entity_type, entity_id, old_values (JSON), new_values (JSON), ip_address, user_agent, created_at | timestamps=false |
| 60 | `system_preferences` | timezone, default_landing_page, default_page_size, enable_auto_refresh, auto_refresh_interval_seconds, enable_draft_forms, draft_auto_save_interval_seconds, session_warning_seconds, updated_by (FK null) | Singleton |
| 61 | `team_role_mappings` | slot_key (unique), role_id (FK cascade), sort_order | |
| 62 | `teams` | name, description, manager_id (FK null), team_leader_id (FK null), department, status (enum), max_members | |
| 63 | `user_audits` | user_id (FK cascade), field_name, old_value, new_value, changed_at, changed_by (FK null), ip_address, user_agent | |
| 64 | `user_column_preferences` | user_id (FK cascade), module, visible_columns (JSON) | unique(user_id, module) |
| 65 | `user_login_logs` | user_id (FK cascade), session_id, login_at, logout_at, ip_address, user_agent, country, country_code, city, role, is_suspicious, suspicious_reason | Many indexes |
| 66 | `user_notification_preferences` | user_id (FK cascade), trigger_id (FK cascade), channel (enum), enabled | unique(user_id, trigger_id, channel) |
| 67 | `user_table_preferences` | user_id (FK cascade), module, per_page | unique(user_id, module) |
| 68 | `users` | name, email (unique), password, phone, country, timezone, cnic_number, additional_notes, status (enum), approved_by (FK null), approved_at, rejected_by, rejection_reason, two_factor_secret, two_factor_enabled, two_factor_confirmed_at, manager_id (FK null), team_leader_id (FK null), employee_number (unique), department, extension, joining_date, terminate_date, team_id (FK null), reports_to (FK null), must_change_password, password_changed_at, locked_until, failed_login_attempts, active_session_token | `rejected_by` has **no FK** |
| 69 | `vas_request_audits` | vas_request_submission_id (FK cascade), field_name, old_value, new_value, changed_at, changed_by (FK null), ip_address, user_agent | |
| 70 | `vas_request_documents` | vas_request_submission_id (FK cascade), doc_key, file_path, file_name, label | Missing mime/size columns — Section 9 |
| 71 | `vas_request_submissions` | account_number, contact_number (added), company_name, request_type, description, additional_notes (added), status (varchar), sales_agent_id/team_leader_id/manager_id/back_office_executive_id (FK null), team_id (FK null), client_id (FK null, added), created_by (FK cascade), activity (added), completion_date (added), remarks (added), submitted_at, approved_at, rejected_at | |
| 72 | `verifiers` | verifier_name, verifier_number, remarks | No FKs, no relationships |

---

## 4. Missing Indexes Analysis

### Already Indexed (Good)
Most FK columns received indexes in migration `2026_03_05_140000_add_performance_indexes_to_submission_tables.php`. Core tables (`users`, `clients`, `lead_submissions`, `field_submissions`, `customer_support_submissions`, `vas_request_submissions`) have composite and single-column indexes on status, created_by, team_id, and other frequently queried columns.

### Still Missing or Recommended

| Table | Column(s) | Recommendation |
|-------|-----------|----------------|
| `notification_logs` | `trigger_key`, `channel`, `status` | Add composite index `(trigger_key, channel)` and single on `status` for dashboard queries |
| `notification_logs` | `created_at` | Add for time-range filtering |
| `email_templates` | `trigger_key` | Add index (used in lookups by trigger key) |
| `escalation_logs` | `status`, `sent_at` | Add for monitoring queries |
| `audit_logs` | Already well-indexed | OK |
| `system_audit_logs` | `entity_type`, `entity_id` | Add composite index for entity lookups |
| `system_audit_logs` | `created_at` | Add for time-range queries |
| `dsp_tracker_entries` | `activity_number`, `account_number` | Add indexes for search/filter |
| `form_drafts` | Already has unique index | OK |
| `client_alerts` | `status`, `expiry_date` | Add composite for dashboard "upcoming alerts" queries |
| `client_alerts` | `manager_id` | Add FK index |
| `expense_attachments` | `expense_id` | Has FK — OK |
| `announcements` | `published_at`, `expire_at` | Add composite for active/scheduled scope queries |
| `announcements` | `is_active`, `is_pinned` | Add for filtered listing |
| `special_requests` | `status`, `created_at` | Add composite for listing/filtering |
| `lead_submission_documents` | `uploaded_by` | Added later — OK |
| `vas_request_documents` | No non-FK indexes | Consider index on `doc_key` if queried |
| `user_login_logs` | `is_suspicious` | Add for security alerting |

---

## 5. Foreign Key Gaps

### Missing FK Constraints

| Table | Column | Points To | Issue |
|-------|--------|-----------|-------|
| `users` | `rejected_by` | `users.id` | Added as plain `unsignedBigInteger`, no FK constraint |
| `lead_submissions` | `approved_by` | `users.id` | Plain column, no FK |
| `lead_submissions` | `rejected_by` | `users.id` | Plain column, no FK |
| `announcements` | `updated_by` | `users.id` | Added via `$table->unsignedBigInteger('updated_by')->nullable()`, no FK |
| `email_templates` | `trigger_key` | `notification_triggers.key` | No FK relationship; relies on application-level integrity |
| `notification_logs` | `trigger_key` | `notification_triggers.key` | Same — no FK |
| `escalation_logs` | `escalation_level` | `escalation_levels.level` | No FK, references by integer value |
| `audit_logs` | `user_id` | `users.id` | Added with FK nullable — OK, but original columns like `user_name` are denormalized strings |
| `dsp_tracker_entries` | Various | — | No FKs beyond `user_id`; all business fields are strings |

### FK Constraints That Were Intentionally Dropped

| Table | Column | Reason |
|-------|--------|--------|
| `cisco_extension_audits` | `cisco_extension_id` | Dropped FK to preserve audit trail when parent is deleted |

---

## 6. Cascade Rule Issues

### Dangerous Cascades (Data Loss Risk)

| Table | Column | Current Rule | Risk | Fix |
|-------|--------|-------------|------|-----|
| `special_requests` | `manager_id` | `cascadeOnDelete` | Deleting a manager **deletes all their special requests** | Change to `nullOnDelete()` |
| `expenses` | `user_id` | `cascadeOnDelete` | Deleting a user **destroys all their expense records** | Consider `nullOnDelete` or restrict |
| `personal_notes` | `user_id` | `cascadeOnDelete` | Acceptable — notes are personal | OK |
| `email_follow_ups` | `created_by` | `cascadeOnDelete` | Deleting a user destroys follow-up history | Consider `nullOnDelete` |
| `otps` | `user_id` | `cascadeOnDelete` | Acceptable — OTPs are ephemeral | OK |
| `user_login_logs` | `user_id` | `cascadeOnDelete` | Destroys login audit trail | Consider keeping with `nullOnDelete` for compliance |
| `user_audits` | `user_id` | `cascadeOnDelete` | Destroys user change history | Use `nullOnDelete` for audit compliance |
| `form_drafts` | `user_id` | `cascadeOnDelete` | Acceptable — drafts are personal | OK |
| `lead_submissions` | `created_by` | `cascadeOnDelete` | Deleting a user **destroys their submissions** | Use `nullOnDelete` |
| `field_submissions` | `created_by` | `cascadeOnDelete` | Same risk | Use `nullOnDelete` |
| `customer_support_submissions` | `created_by` | `cascadeOnDelete` | Same risk | Use `nullOnDelete` |
| `vas_request_submissions` | `created_by` | `cascadeOnDelete` | Same risk | Use `nullOnDelete` |

### Restrict Without Escape Hatch

| Table | Column | Issue |
|-------|--------|-------|
| `library_documents` | `uploaded_by` | `RESTRICT` — cannot delete user if they uploaded any document |
| `library_document_versions` | `uploaded_by` | Same |

---

## 7. Mass Assignment Risks

### Models Using `$fillable` (Good Practice)
All 64 models use `$fillable` arrays — no model uses `$guarded = []` globally. This is good.

### Potentially Over-Permissive `$fillable`

| Model | Column in `$fillable` | Risk |
|-------|----------------------|------|
| `User` | `status`, `approved_by`, `must_change_password`, `locked_until`, `failed_login_attempts` | Admin-level fields in mass-assignable list — could be exploited if request data is passed directly |
| `SecuritySetting` | All policy columns | Admin-only, but should verify controller-level guards |
| `NotificationSetting` | All master toggles | Admin-only |
| `SlaRule` | `sla_duration_minutes`, `warning_threshold_minutes` | Admin-only |
| `CiscoExtension` | `password` | Sensitive credential in fillable |
| `EscalationLevel` | `is_active` | Admin-only toggle |
| `AuditLog` | `user_id`, `user_name`, `ip`, etc. | Audit fields should not be mass-assignable from user input |
| `SystemAuditLog` | All fields | Should be write-only via `record()` static method |

**General Recommendation:** Use form requests with explicit `$request->validated()` in all controllers (appears to already be done based on controller patterns). Consider splitting fillable vs. admin-only fields using policy checks at the controller/request layer.

---

## 8. Relationship Integrity Issues

### Missing Relationships in Models

| Model | Missing Relationship | Expected |
|-------|---------------------|----------|
| `Otp` | `user()` | `belongsTo(User::class)` — has `user_id` FK |
| `AuditLog` | `user()` | `belongsTo(User::class)` — has `user_id` FK |
| `EmailTemplate` | `trigger()` | Could relate to `NotificationTrigger` via `trigger_key` |
| `NotificationEscalation` | `updater()` | `belongsTo(User::class, 'updated_by')` |
| `NotificationLog` | No relationships | Could have `trigger()` and scopes |
| `UserColumnPreference` | `user()` | `belongsTo(User::class)` — has `user_id` FK |
| `Verifier` | None defined | Standalone table — may be intentional |
| `DspTrackerEntry` | Only `user()` | No relationships to clients/submissions |
| `Country` | None | No reverse relationships (users reference country by string, not FK) |

### Broken Relationships

| Model | Method | Issue |
|-------|--------|-------|
| `User` | `leadColumnPreference()` | References `LeadColumnPreference::class` — class does not exist. Should be `LeadSubmissionColumnPreference::class` | **CRIT-2** |

### Relationship / Schema Mismatches

| Model | Relationship | Issue |
|-------|-------------|-------|
| `Account` | `belongsTo(User::class, 'assigned_csr_id')` | Column doesn't exist in migration | **CRIT-1** |
| `User` | `reportsTo()` → `belongsTo(User::class, 'reports_to')` | Column exists ✓ |
| `User` | `directReports()` → `hasMany(User::class, 'reports_to')` | Column exists ✓ |
| `Client` | Multiple submission relationships | All reference `created_by` — these use `hasMany` with a local key match that works through account_number |

### Inverse Relationships Not Defined

Many `belongsTo` relationships lack corresponding `hasMany`/`hasOne` on the parent:

| Parent Model | Missing `hasMany` for |
|-------------|----------------------|
| `User` | `expenses`, `personalNotes`, `emailFollowUps`, `otps`, `loginLogs`, `userAudits`, `formDrafts`, `dspTrackerEntries` |
| `User` | `ciscoExtensions` (as assignedTo), `ciscoExtensions` (as teamLeader), `ciscoExtensions` (as manager) |
| `User` | `fieldSubmissions` (as field_executive), `customerSupportSubmissions` (as csr) |

This is not necessarily a bug (inverse relationships are only needed if queried from the parent), but it limits query flexibility.

---

## 9. Schema / Migration Issues

### 9.1 String Date Columns in `dsp_tracker_entries`

**File:** `2026_02_14_*_create_dsp_tracker_entries_table.php`

```php
$table->string('appointment_date')->nullable();
$table->string('appointment_time')->nullable();
$table->string('uploaded_at')->nullable();
```

**Impact:** Cannot use database-level date functions, sorting is lexicographic, no validation at DB layer.

**Recommendation:** Convert to `date`, `time`, and `timestamp` columns respectively.

---

### 9.2 `service_categories` Missing `description` Column

**Model:** `ServiceCategory` has `'description'` in `$fillable`.  
**Migration:** Only creates `name`, `slug`, `is_active`, `sort_order`.

**Impact:** Setting description will silently fail or throw in strict mode.

---

### 9.3 `vas_request_documents` Missing `mime` and `size` Columns

**Model:** `VasRequestDocument` does not include `mime` or `size` in `$fillable`.  
**Migration:** Only creates `doc_key`, `file_path`, `file_name`, `label`.  

All other document tables (`lead_submission_documents`, `field_submission_documents`, `special_request_documents`) include `mime` and `size` columns.

**Recommendation:** Add `mime` and `size` columns for consistency.

---

### 9.4 Enum vs. VARCHAR Inconsistency for Status Columns

Several tables started with `enum` status columns and were later converted to `varchar`:

| Table | Migration | Change |
|-------|-----------|--------|
| `lead_submissions` | `alter_lead_submissions_status_to_string.php` | enum → varchar(50) |
| `customer_support_submissions` | `change_status_to_varchar…` | enum → varchar(50) |
| `vas_request_submissions` | `change_vas…status_to_varchar…` | enum → varchar |

Other tables still use enums:
- `personal_notes.status` (enum)
- `personal_notes.priority` (enum)
- `teams.status` (enum)
- `users.status` (enum)
- `field_submissions.status` (enum)

**Recommendation:** Standardize — either use varchar everywhere with model-level constants (preferred for flexibility), or keep enum with a documented migration strategy.

---

### 9.5 Duplicate Unique Constraint Toggling on `lead_submission_documents`

Three separate migrations touch the `(lead_submission_id, doc_key)` uniqueness:
1. Added unique constraint
2. Dropped it
3. Re-added it

This suggests schema uncertainty. The final state has the unique constraint.

---

### 9.6 Multiple Migration Amendments Pattern

Several tables have 5–8 amendment migrations (e.g., `customer_support_submissions` has ~7). While functional, this makes the schema hard to reason about from migrations alone.

**Recommendation:** For development environments, consider squashing migrations.

---

## 10. Model Code Issues

### 10.1 `Expense::getVatAmountAttribute()` Overrides Database Column

**File:** `app/Models/Expense.php`

```php
public function getVatAmountAttribute(): float
{
    return round(($this->attributes['vat_amount'] ?? 0) / 100 * ($this->attributes['amount_without_vat'] ?? 0), 2);
}
```

The accessor uses the same name as the database column `vat_amount`. This means:
- Reading `$expense->vat_amount` returns the **computed** value (percentage × amount), not the stored value
- The stored value in the DB is treated as a **percentage**, not a monetary amount
- This is confusing and undocumented — `vat_amount` column name implies a currency value

**Recommendation:** Rename the column to `vat_percentage` or `vat_rate`, and rename the accessor to `getComputedVatAmountAttribute()` / use an appended attribute.

---

### 10.2 `LeadSubmission::scopeVisibleTo()` Returns All Records

```php
public function scopeVisibleTo(Builder $query, User $user): Builder
{
    // Scoping is handled at the service/controller layer
    return $query;
}
```

Unlike other submission models that implement role-based filtering, `LeadSubmission` does no filtering. The comment suggests this is intentional, but it's a potential authorization gap if any code path relies on this scope for access control.

---

### 10.3 `CustomerSupportSubmission::booted()` Ticket Number Generation

```php
static::creating(function (self $model) {
    if (empty($model->ticket_number)) {
        $model->ticket_number = 'CS-' . now()->format('Y') . '-' . str_pad(
            static::withoutGlobalScopes()->count() + 1, 4, '0', STR_PAD_LEFT
        );
    }
});
```

**Issue:** Uses `count() + 1` which is not atomic — concurrent inserts can generate duplicate ticket numbers. The column appears to have no unique constraint.

**Recommendation:** Use a database sequence, auto-increment, or add a unique constraint with retry logic.

---

### 10.4 Models Without `$casts` for JSON/Date Columns

| Model | Column | Missing Cast |
|-------|--------|-------------|
| `DspTrackerEntry` | All date-like string columns | `$casts` is an empty array |
| `Verifier` | — | No casts at all (acceptable as all are strings) |

---

## 11. Seeder & Factory Issues

### 11.1 `UserFactory` Incomplete

**File:** `database/factories/UserFactory.php`

The factory only sets `name`, `email`, `password`, `remember_token`. It's missing:

| Field | Issue |
|-------|-------|
| `status` | Migration defaults to `'pending'` — factory should set `'approved'` for test users |
| `phone` | Not set |
| `country` | Not set |
| `timezone` | Not set |
| `employee_number` | Not set — has unique constraint, will fail on multiple creates |

**Recommendation:** Expand factory with all required fields and states:
```php
'status' => 'approved',
'employee_number' => fake()->unique()->numerify('EMP-####'),
```

---

### 11.2 Seeders Not Called from `DatabaseSeeder`

**File:** `database/seeders/DatabaseSeeder.php`

The following seeders exist but are NOT called from `DatabaseSeeder`:

| Seeder | Purpose | Should Include? |
|--------|---------|----------------|
| `SecuritySettingsSeeder` | Creates singleton security config | **Yes** — required for login to work |
| `SystemPreferencesSeeder` | Creates singleton prefs | **Yes** — required for UI |
| `EscalationLevelSeeder` | Default escalation levels | **Yes** — required for SLA module |
| `SlaRulesSeeder` | Default SLA timers | **Yes** — required for SLA module |
| `NotificationConfigSeeder` | Notification settings + triggers + templates | **Yes** — required for notifications |
| `LibrarySeeder` | Demo library data | No — demo data |
| `AnnouncementSeeder` | Demo announcements | No — demo data |
| `AuditLogsDemoSeeder` | Demo audit log entries | No — demo data |
| `LeadPermissionSeeder` | Lead-specific permissions | Redundant — `PermissionSeeder` already creates these |
| `TinkerDemoHierarchySeeder` | Demo user hierarchy | No — demo data |

---

### 11.3 Hardcoded Email in Seeders

`SlaRulesSeeder` hardcodes `'order@astonhill.ae'` for all notification_email values. This should be configurable or pulled from `.env`.

---

## 12. Redundant / Duplicate Data

### 12.1 CSR Name Duplication (3 locations)

CSR (Customer Service Representative) data is stored in three overlapping ways:

| Location | Columns/Data |
|----------|-------------|
| `clients` table | `csr_name_1`, `csr_name_2`, `csr_name_3` (plain strings) |
| `client_company_details` table | `csr_name_1`, `csr_name_2`, `csr_name_3` (plain strings) |
| `client_csrs` pivot table | `client_id` → `user_id` (proper relational) |

**Impact:** Data can drift between the three sources. The pivot table (`client_csrs`) is the correct normalized approach; the string columns are legacy/denormalized.

**Recommendation:** Migrate data from string columns to the pivot table, then drop the string columns.

---

### 12.2 `customer_support_submissions` — Duplicate CSR Reference

| Column | Type | Purpose |
|--------|------|---------|
| `csr_name` | varchar | String name of the CSR |
| `csr_id` | FK → users | Proper FK to the CSR user |

**Impact:** `csr_name` can drift from the actual user name.

**Recommendation:** Drop `csr_name` and derive it via `csr_id` relationship.

---

### 12.3 Submission Tables Repeat Client Data

All submission tables (`lead_submissions`, `field_submissions`, `customer_support_submissions`, `vas_request_submissions`, `special_requests`) store `company_name` and `account_number` directly, despite having a `client_id` FK (added later).

This is a common denormalization choice for historical accuracy (client name may change), but it means:
- Updates to `clients.company_name` don't propagate
- Data can drift

**Recommendation:** Document this as intentional denormalization. Alternatively, use `client_id` as the source of truth and join when needed.

---

## 13. Missing Soft Deletes

**No model in the entire codebase uses Laravel's `SoftDeletes` trait.**

This is significant for a CRM because:
1. Deleting a user with `cascadeOnDelete` FKs will destroy all related business data permanently
2. The `EscalationLevel` model queries `whereNull('deleted_at')` on users (CRIT-5), suggesting soft deletes were planned but never implemented
3. Audit compliance typically requires soft deletes for business entities

### Recommended Soft Delete Candidates

| Model | Reason |
|-------|--------|
| `User` | Most critical — prevents cascade data loss, enables "deactivate" workflow |
| `Client` | Business entity — should never be hard deleted |
| `LeadSubmission` | Business records |
| `FieldSubmission` | Business records |
| `CustomerSupportSubmission` | Business records |
| `VasRequestSubmission` | Business records |
| `SpecialRequest` | Business records |
| `Expense` | Financial records |
| `Team` | Organizational entity |
| `LibraryDocument` | Document management |
| `Announcement` | Has `archived_at` but no soft delete |

**Alternative:** The app may already handle this by using `status` fields (e.g., `users.status = 'terminated'`) instead of deleting. If so, the dangerous `cascadeOnDelete` rules are only a risk if someone force-deletes at the database level.

---

## 14. Schema Improvement Recommendations

### Priority 1 — Must Fix (Data Loss / Runtime Errors)

| # | Issue | Action |
|---|-------|--------|
| 1 | `accounts` table empty schema | Add columns or remove dead model |
| 2 | `User::leadColumnPreference()` broken class reference | Fix to `LeadSubmissionColumnPreference::class` |
| 3 | `special_requests.manager_id` cascade on delete | Change to `nullOnDelete()` |
| 4 | `library_documents.uploaded_by` restrictive FK | Change to `nullOnDelete()` |
| 5 | `EscalationLevel` queries `deleted_at` on users | Remove query or add SoftDeletes to User |
| 6 | `created_by` cascadeOnDelete on all submission tables | Change to `nullOnDelete()` |

### Priority 2 — Should Fix (Data Integrity)

| # | Issue | Action |
|---|-------|--------|
| 7 | Missing FK on `users.rejected_by` | Add FK constraint |
| 8 | Missing FK on `lead_submissions.approved_by` / `rejected_by` | Add FK constraints |
| 9 | Missing FK on `announcements.updated_by` | Add FK constraint |
| 10 | `service_categories` missing `description` column | Add migration |
| 11 | `vas_request_documents` missing mime/size | Add columns |
| 12 | `Expense.getVatAmountAttribute` overrides DB column | Rename column to `vat_percentage` |
| 13 | Ticket number generation race condition | Add unique constraint + retry |
| 14 | `UserFactory` incomplete | Add required fields |
| 15 | Missing seeders in `DatabaseSeeder` | Add SecuritySettings, SystemPreferences, EscalationLevel, SlaRules, NotificationConfig |

### Priority 3 — Should Improve (Schema Quality)

| # | Issue | Action |
|---|-------|--------|
| 16 | String dates in `dsp_tracker_entries` | Convert to proper date/time types |
| 17 | CSR name duplication across 3 tables | Consolidate to pivot table |
| 18 | Inconsistent enum vs. varchar for status | Standardize to varchar |
| 19 | Missing indexes on notification/audit tables | Add per Section 4 |
| 20 | Consider SoftDeletes for core models | Add trait + migration per Section 13 |
| 21 | `user_login_logs` cascade on user delete | Change to preserve audit trail |
| 22 | Missing inverse relationships | Add where needed per Section 8 |
| 23 | Squash migrations for clarity | Run `php artisan schema:dump` |

---

## Appendix A — Full Table Inventory

### Models → Tables Mapping

| Model | Table | Timestamps | SoftDeletes |
|-------|-------|-----------|-------------|
| Account | accounts | ✅ | ❌ |
| Announcement | announcements | ✅ | ❌ |
| AnnouncementAcknowledgement | announcement_acknowledgements | ❌ | ❌ |
| AuditLog | audit_logs | ✅ | ❌ |
| CiscoExtension | cisco_extensions | ✅ | ❌ |
| CiscoExtensionAudit | cisco_extension_audits | ✅ | ❌ |
| Client | clients | ✅ | ❌ |
| ClientAddress | client_addresses | ✅ | ❌ |
| ClientAlert | client_alerts | ✅ | ❌ |
| ClientAudit | client_audits | ✅ | ❌ |
| ClientCompanyDetail | client_company_details | ✅ | ❌ |
| ClientContact | client_contacts | ✅ | ❌ |
| ClientCsr | client_csrs | ✅ | ❌ |
| Country | countries | ✅ | ❌ |
| CustomerSupportSubmission | customer_support_submissions | ✅ | ❌ |
| CustomerSupportSubmissionAudit | customer_support_submission_audits | ✅ | ❌ |
| DropdownOption | dropdown_options | ✅ | ❌ |
| DspTrackerEntry | dsp_tracker_entries | ✅ | ❌ |
| EmailFollowUp | email_follow_ups | ✅ | ❌ |
| EmailTemplate | email_templates | ✅ | ❌ |
| EscalationLevel | escalation_levels | ✅ | ❌ |
| EscalationLog | escalation_logs | ❌ | ❌ |
| Expense | expenses | ✅ | ❌ |
| ExpenseAttachment | expense_attachments | ✅ | ❌ |
| ExpenseAudit | expense_audits | ✅ | ❌ |
| FieldSubmission | field_submissions | ✅ | ❌ |
| FieldSubmissionAudit | field_submission_audits | ✅ | ❌ |
| FieldSubmissionDocument | field_submission_documents | ✅ | ❌ |
| FormDraft | form_drafts | ✅ | ❌ |
| LeadSubmission | lead_submissions | ✅ | ❌ |
| LeadSubmissionAudit | lead_submission_audits | ✅ | ❌ |
| LeadSubmissionColumnPreference | lead_submission_column_preferences | ✅ | ❌ |
| LeadSubmissionDocument | lead_submission_documents | ✅ | ❌ |
| LibraryCategory | library_categories | ✅ | ❌ |
| LibraryDocument | library_documents | ✅ | ❌ |
| LibraryDocumentVersion | library_document_versions | ❌ | ❌ |
| NotificationEscalation | notification_escalations | ✅ | ❌ |
| NotificationLog | notification_logs | ✅ | ❌ |
| NotificationSetting | notification_settings | ✅ | ❌ |
| NotificationTrigger | notification_triggers | ✅ | ❌ |
| Otp | otps | ✅ | ❌ |
| PersonalNote | personal_notes | ✅ | ❌ |
| Role | roles | ✅ | ❌ |
| SecuritySetting | security_settings | ✅ | ❌ |
| ServiceCategory | service_categories | ✅ | ❌ |
| ServiceType | service_types | ✅ | ❌ |
| SlaRule | sla_rules | ✅ | ❌ |
| SpecialRequest | special_requests | ✅ | ❌ |
| SpecialRequestAudit | special_request_audits | ✅ | ❌ |
| SpecialRequestDocument | special_request_documents | ✅ | ❌ |
| SystemAuditLog | system_audit_logs | ❌ | ❌ |
| SystemPreference | system_preferences | ✅ | ❌ |
| Team | teams | ✅ | ❌ |
| TeamRoleMapping | team_role_mappings | ✅ | ❌ |
| User | users | ✅ | ❌ |
| UserAudit | user_audits | ✅ | ❌ |
| UserColumnPreference | user_column_preferences | ✅ | ❌ |
| UserLoginLog | user_login_logs | ✅ | ❌ |
| UserNotificationPreference | user_notification_preferences | ✅ | ❌ |
| UserTablePreference | user_table_preferences | ✅ | ❌ |
| VasRequestAudit | vas_request_audits | ✅ | ❌ |
| VasRequestDocument | vas_request_documents | ✅ | ❌ |
| VasRequestSubmission | vas_request_submissions | ✅ | ❌ |
| Verifier | verifiers | ✅ | ❌ |

---

*End of audit report. No files were modified during this analysis.*
