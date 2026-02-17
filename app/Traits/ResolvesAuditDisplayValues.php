<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves raw ID values in audit log rows to human-readable display names,
 * and adds a field_label key so the frontend can display field names correctly.
 *
 * Usage: In any controller with an audits() method, use this trait and call
 *   $data = $this->resolveAuditDisplayValues($data);
 * after mapping the audit rows to arrays.
 */
trait ResolvesAuditDisplayValues
{
    /**
     * Fields whose values are user IDs (resolve to user.name).
     */
    private static array $userIdFields = [
        'executive_id',
        'back_office_executive_id',
        'sales_agent_id',
        'team_leader_id',
        'manager_id',
        'created_by',
        'updated_by',
        'field_executive_id',
        'csr_id',
        'approved_by',
        'rejected_by',
        'assigned_to',
        'user_id',
        'changed_by',
        'reports_to',
    ];

    /**
     * Wizard step number → human-readable name (lead submissions).
     */
    private static array $stepLabels = [
        '1' => 'Company & Contact Details',
        '2' => 'Service Selection',
        '3' => 'Additional Details & Documents',
        '4' => 'Review & Submit',
    ];

    /**
     * Comprehensive field_name → human-readable label mapping.
     * The frontend also has a parallel map (COMMON_LABELS) but this is the
     * authoritative server-side version included in the API response.
     */
    private static array $fieldLabels = [
        // Submission workflow
        'step'                      => 'Submission Step',
        'wizard_step'               => 'Submission Step',
        'status'                    => 'Status',
        'submitted_at'              => 'Submitted At',
        'status_changed_at'         => 'Status Changed At',
        'submission_type'           => 'Submission Type',
        'submission_date_from'      => 'Submission Date',

        // Company / contact
        'company_name'              => 'Company Name',
        'account_number'            => 'Account Number',
        'authorized_signatory_name' => 'Authorized Signatory',
        'email'                     => 'Email',
        'contact_number_gsm'        => 'Contact Number (GSM)',
        'alternate_contact_number'  => 'Alternate Contact Number',
        'address'                   => 'Address',
        'emirate'                   => 'Emirate',
        'location_coordinates'      => 'Location Coordinates',

        // Service
        'category'                  => 'Service Category',
        'category_name'             => 'Service Category',
        'type'                      => 'Service Type',
        'type_name'                 => 'Service Type',
        'service_category_id'       => 'Service Category',
        'service_type_id'           => 'Service Type',
        'service_type'              => 'Service Type',
        'product'                   => 'Product',
        'offer'                     => 'Offer',
        'mrc_aed'                   => 'MRC (AED)',
        'quantity'                  => 'Quantity',
        'ae_domain'                 => 'AE Domain',
        'gaid'                      => 'GAID',
        'remarks'                   => 'Remarks',

        // People / hierarchy
        'sales_agent_id'            => 'Sales Agent',
        'sales_agent'               => 'Sales Agent',
        'team_leader_id'            => 'Team Leader',
        'team_leader'               => 'Team Leader',
        'manager_id'                => 'Manager',
        'manager'                   => 'Manager',
        'executive_id'              => 'Back Office Executive',
        'executive'                 => 'Back Office Executive',
        'back_office_executive_id'  => 'Back Office Executive',
        'field_executive_id'        => 'Field Agent',
        'csr_id'                    => 'Customer Support Representative',
        'created_by'                => 'Created By',
        'updated_by'                => 'Updated By',
        'approved_by'               => 'Approved By',
        'rejected_by'               => 'Rejected By',
        'assigned_to'               => 'Assigned To',
        'creator'                   => 'Created By',

        // Back office fields
        'call_verification'         => 'Call Verification',
        'pending_from_sales'        => 'Pending From Sales',
        'documents_verification'    => 'Documents Verification',
        'back_office_notes'         => 'Back Office Notes',
        'back_office_account'       => 'Back Office Account',
        'work_order'                => 'Work Order',
        'du_status'                 => 'DU Status',
        'completion_date'           => 'Completion Date',
        'du_remarks'                => 'DU Remarks',
        'additional_note'           => 'Additional Note',

        // Customer support
        'issue_description'         => 'Issue Description',
        'issue_category'            => 'Issue Category',
        'workflow_status'           => 'Workflow Status',
        'pending_with'              => 'Pending With',
        'request_type'              => 'Request Type',

        // VAS
        'sim_type'                  => 'SIM Type',
        'plan_name'                 => 'Plan Name',
        'monthly_charges'           => 'Monthly Charges',
        'contract_period'           => 'Contract Period',
        'activation_date'           => 'Activation Date',
        'request_description'       => 'Request Description',

        // Field submissions
        'field_status'              => 'Field Status',
        'activity'                  => 'Activity',

        // Organization
        'department_id'             => 'Department',
        'team_id'                   => 'Team',
        'user_id'                   => 'User',
        'reports_to'                => 'Reports To',

        // Dates
        'approved_at'               => 'Approved At',
        'rejected_at'               => 'Rejected At',

        // Expense Tracker
        'expense_date'              => 'Expense Date',
        'product_category'          => 'Product Category',
        'product_description'       => 'Product Description',
        'invoice_number'            => 'Invoice Number',
        'vat_amount'                => 'VAT Amount',
        'amount_without_vat'        => 'Amount Without VAT',
        'vat_amount_currency'       => 'VAT Amount (Currency)',
        'full_amount'               => 'Full Amount',
        'receipt_image'             => 'Receipt Image',
        'payment_type'              => 'Payment Type',
        'remark'                    => 'Remark',
        'supplier'                  => 'Supplier',

        // Cisco Extensions
        'extension'                 => 'Extension',
        'landline_number'           => 'Landline Number',
        'gateway'                   => 'Gateway',
        'username'                  => 'Username',
        'password'                  => 'Password',
        'usage'                     => 'Usage',
        'assigned_to_name'          => 'Assigned To',
        'comment'                   => 'Comment',

        // User management
        'name'                      => 'Name',
        'phone'                     => 'Phone',
        'role'                      => 'Role',
        'designation'               => 'Designation',
        'employee_code'             => 'Employee Code',
        'changed_by'                => 'Changed By',
        'reports_to'                => 'Reports To',
        'max_members'               => 'Max Members',
        'description'               => 'Description',
        'contact_number'            => 'Contact Number',
        'complete_address'          => 'Complete Address',
        'meeting_date'              => 'Meeting Date',
        'emirates'                  => 'Emirates',
    ];

    /**
     * Status value → human-readable label mapping.
     */
    private static array $statusLabels = [
        'draft'       => 'Draft',
        'submitted'   => 'Submitted',
        'new'         => 'New',
        'in_progress' => 'In Progress',
        'in_review'   => 'In Review',
        'approved'    => 'Approved',
        'rejected'    => 'Rejected',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
        'closed'      => 'Closed',
        'pending'     => 'Pending',
        'on_hold'     => 'On Hold',
        'escalated'   => 'Escalated',
        'resolved'    => 'Resolved',
        'active'      => 'Active',
        'inactive'    => 'Inactive',
    ];

    /**
     * Post-process a collection of audit row arrays:
     * - Replaces raw foreign-key IDs with the display name of the referenced entity.
     * - Adds a 'field_label' key with the human-readable label for the field.
     * - Resolves step numbers to step names.
     * - Resolves status codes to readable labels.
     * - Resolves IDs inside JSON payload sub-fields.
     */
    protected function resolveAuditDisplayValues(Collection $rows): Collection
    {
        // 1. Collect all unique IDs that need resolving (including from JSON payloads)
        $userIds = collect();
        $categoryIds = collect();
        $typeIds = collect();
        $teamIds = collect();
        $departmentIds = collect();

        foreach ($rows as $row) {
            $field = $row['field_name'] ?? null;
            if (! $field) {
                continue;
            }

            if (in_array($field, self::$userIdFields, true)) {
                $this->collectId($userIds, $row['old_value']);
                $this->collectId($userIds, $row['new_value']);
            } elseif ($field === 'service_category_id') {
                $this->collectId($categoryIds, $row['old_value']);
                $this->collectId($categoryIds, $row['new_value']);
            } elseif ($field === 'service_type_id') {
                $this->collectId($typeIds, $row['old_value']);
                $this->collectId($typeIds, $row['new_value']);
            } elseif ($field === 'team_id') {
                $this->collectId($teamIds, $row['old_value']);
                $this->collectId($teamIds, $row['new_value']);
            } elseif ($field === 'department_id') {
                $this->collectId($departmentIds, $row['old_value']);
                $this->collectId($departmentIds, $row['new_value']);
            }

            // Also scan JSON payload fields for embedded IDs
            if ($field === 'payload') {
                foreach (['old_value', 'new_value'] as $valKey) {
                    $parsed = $this->tryParseJson($row[$valKey] ?? null);
                    if (! is_array($parsed)) {
                        continue;
                    }
                    foreach ($parsed as $subField => $subVal) {
                        if (in_array($subField, self::$userIdFields, true)) {
                            $this->collectId($userIds, $subVal);
                        } elseif ($subField === 'service_category_id') {
                            $this->collectId($categoryIds, $subVal);
                        } elseif ($subField === 'service_type_id') {
                            $this->collectId($typeIds, $subVal);
                        } elseif ($subField === 'team_id') {
                            $this->collectId($teamIds, $subVal);
                        } elseif ($subField === 'department_id') {
                            $this->collectId($departmentIds, $subVal);
                        }
                    }
                }
            }
        }

        // 2. Batch-fetch names from the database
        $userNames = $userIds->isNotEmpty()
            ? User::whereIn('id', $userIds->unique()->all())->pluck('name', 'id')
            : collect();

        $categoryNames = $categoryIds->isNotEmpty()
            ? DB::table('service_categories')->whereIn('id', $categoryIds->unique()->all())->pluck('name', 'id')
            : collect();

        $typeNames = $typeIds->isNotEmpty()
            ? DB::table('service_types')->whereIn('id', $typeIds->unique()->all())->pluck('name', 'id')
            : collect();

        $teamNames = $teamIds->isNotEmpty()
            ? DB::table('teams')->whereIn('id', $teamIds->unique()->all())->pluck('name', 'id')
            : collect();

        $departmentNames = $departmentIds->isNotEmpty()
            ? DB::table('departments')->whereIn('id', $departmentIds->unique()->all())->pluck('name', 'id')
            : collect();

        // 3. Replace raw values with display names + add field_label
        return $rows->map(function (array $row) use ($userNames, $categoryNames, $typeNames, $teamNames, $departmentNames) {
            $field = $row['field_name'] ?? null;
            if (! $field) {
                return $row;
            }

            // Add human-readable field label
            $row['field_label'] = $this->getFieldLabel($field);

            // Resolve values based on field type
            if (in_array($field, self::$userIdFields, true)) {
                $row['old_value'] = $this->resolveValue($row['old_value'], $userNames);
                $row['new_value'] = $this->resolveValue($row['new_value'], $userNames);
            } elseif ($field === 'service_category_id') {
                $row['old_value'] = $this->resolveValue($row['old_value'], $categoryNames);
                $row['new_value'] = $this->resolveValue($row['new_value'], $categoryNames);
            } elseif ($field === 'service_type_id') {
                $row['old_value'] = $this->resolveValue($row['old_value'], $typeNames);
                $row['new_value'] = $this->resolveValue($row['new_value'], $typeNames);
            } elseif ($field === 'team_id') {
                $row['old_value'] = $this->resolveValue($row['old_value'], $teamNames);
                $row['new_value'] = $this->resolveValue($row['new_value'], $teamNames);
            } elseif ($field === 'department_id') {
                $row['old_value'] = $this->resolveValue($row['old_value'], $departmentNames);
                $row['new_value'] = $this->resolveValue($row['new_value'], $departmentNames);
            } elseif ($field === 'step' || $field === 'wizard_step') {
                $row['old_value'] = self::$stepLabels[$row['old_value']] ?? $row['old_value'];
                $row['new_value'] = self::$stepLabels[$row['new_value']] ?? $row['new_value'];
            } elseif ($field === 'status' || $field === 'workflow_status' || $field === 'field_status' || $field === 'du_status') {
                $row['old_value'] = $this->resolveStatusValue($row['old_value']);
                $row['new_value'] = $this->resolveStatusValue($row['new_value']);
            }

            // Resolve IDs inside JSON payload sub-fields
            if ($field === 'payload') {
                $row['old_value'] = $this->resolveJsonPayload($row['old_value'], $userNames, $categoryNames, $typeNames, $teamNames, $departmentNames);
                $row['new_value'] = $this->resolveJsonPayload($row['new_value'], $userNames, $categoryNames, $typeNames, $teamNames, $departmentNames);
            }

            // Resolve attachment/document arrays to comma-separated file names
            if (in_array($field, ['attachments', 'documents', 'files', 'uploaded_files'], true)) {
                $row['old_value'] = $this->resolveAttachmentValue($row['old_value']);
                $row['new_value'] = $this->resolveAttachmentValue($row['new_value']);
            }

            return $row;
        });
    }

    /**
     * Get the human-readable label for a field name.
     */
    private function getFieldLabel(string $fieldName): string
    {
        if (isset(self::$fieldLabels[$fieldName])) {
            return self::$fieldLabels[$fieldName];
        }

        // Fallback: strip _id suffix, replace underscores with spaces, title-case
        return ucwords(str_replace('_', ' ', preg_replace('/_id$/', '', $fieldName)));
    }

    /**
     * Resolve a status code to a human-readable label.
     */
    private function resolveStatusValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        $key = strtolower(trim((string) $value));

        return self::$statusLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Resolve foreign-key IDs inside a JSON payload string.
     * Returns the modified JSON string with IDs replaced by names.
     */
    private function resolveJsonPayload(
        mixed $value,
        Collection $userNames,
        Collection $categoryNames,
        Collection $typeNames,
        Collection $teamNames,
        Collection $departmentNames
    ): mixed {
        $parsed = $this->tryParseJson($value);
        if (! is_array($parsed)) {
            return $value;
        }

        $modified = false;
        foreach ($parsed as $subField => $subVal) {
            if ($subVal === null || $subVal === '') {
                continue;
            }
            if (in_array($subField, self::$userIdFields, true)) {
                $resolved = $this->resolveValue($subVal, $userNames);
                if ($resolved !== $subVal) {
                    $parsed[$subField] = $resolved;
                    $modified = true;
                }
            } elseif ($subField === 'service_category_id') {
                $resolved = $this->resolveValue($subVal, $categoryNames);
                if ($resolved !== $subVal) {
                    $parsed[$subField] = $resolved;
                    $modified = true;
                }
            } elseif ($subField === 'service_type_id') {
                $resolved = $this->resolveValue($subVal, $typeNames);
                if ($resolved !== $subVal) {
                    $parsed[$subField] = $resolved;
                    $modified = true;
                }
            } elseif ($subField === 'team_id') {
                $resolved = $this->resolveValue($subVal, $teamNames);
                if ($resolved !== $subVal) {
                    $parsed[$subField] = $resolved;
                    $modified = true;
                }
            } elseif ($subField === 'department_id') {
                $resolved = $this->resolveValue($subVal, $departmentNames);
                if ($resolved !== $subVal) {
                    $parsed[$subField] = $resolved;
                    $modified = true;
                }
            }
        }

        return $modified ? json_encode($parsed) : $value;
    }

    /**
     * Try to parse a value as JSON. Returns the decoded array or null.
     */
    private function tryParseJson(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * Add a numeric-looking value to the collection of IDs to resolve.
     */
    private function collectId(Collection $ids, mixed $value): void
    {
        if ($value !== null && $value !== '' && is_numeric($value)) {
            $ids->push((int) $value);
        }
    }

    /**
     * Replace a raw ID with the display name from the lookup, or keep original.
     */
    private function resolveValue(mixed $value, Collection $lookup): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (is_numeric($value)) {
            return $lookup->get((int) $value, $value);
        }

        return $value;
    }

    /**
     * Resolve an attachment array value to comma-separated file names.
     * Handles both JSON-encoded strings and actual arrays.
     */
    private function resolveAttachmentValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $arr = $value;
        if (is_string($value)) {
            $trimmed = trim($value);
            if (str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $arr = $decoded;
                } else {
                    return $value;
                }
            } else {
                return $value;
            }
        }

        if (! is_array($arr)) {
            return $value;
        }

        $names = [];
        foreach ($arr as $item) {
            if (is_array($item)) {
                $name = $item['file_name'] ?? $item['original_name'] ?? $item['name'] ?? $item['label'] ?? null;
                if ($name) {
                    $names[] = $name;
                }
            }
        }

        return count($names) > 0 ? implode(', ', $names) : $value;
    }

    /**
     * Resolve object-based audit entries (used by Expense and Extensions).
     * These audits store full old_values / new_values JSON objects instead of
     * individual field-level rows. This method resolves IDs and adds field_labels.
     *
     * Each row is expected to have: id, action, old_values (array), new_values (array),
     * user_name, created_at.
     *
     * Returns the collection with resolved values and a field_labels map.
     */
    protected function resolveObjectBasedAuditValues(Collection $rows): Collection
    {
        // 1. Collect all IDs from old_values and new_values
        $userIds = collect();
        $categoryIds = collect();
        $typeIds = collect();
        $teamIds = collect();
        $departmentIds = collect();

        foreach ($rows as $row) {
            foreach (['old_values', 'new_values'] as $key) {
                $obj = $row[$key] ?? null;
                if (! is_array($obj)) {
                    continue;
                }
                foreach ($obj as $field => $val) {
                    if (in_array($field, self::$userIdFields, true)) {
                        $this->collectId($userIds, $val);
                    } elseif ($field === 'service_category_id') {
                        $this->collectId($categoryIds, $val);
                    } elseif ($field === 'service_type_id') {
                        $this->collectId($typeIds, $val);
                    } elseif ($field === 'team_id') {
                        $this->collectId($teamIds, $val);
                    } elseif ($field === 'department_id') {
                        $this->collectId($departmentIds, $val);
                    }
                }
            }
        }

        // 2. Batch-fetch
        $userNames = $userIds->isNotEmpty()
            ? User::whereIn('id', $userIds->unique()->all())->pluck('name', 'id')
            : collect();
        $categoryNames = $categoryIds->isNotEmpty()
            ? DB::table('service_categories')->whereIn('id', $categoryIds->unique()->all())->pluck('name', 'id')
            : collect();
        $typeNames = $typeIds->isNotEmpty()
            ? DB::table('service_types')->whereIn('id', $typeIds->unique()->all())->pluck('name', 'id')
            : collect();
        $teamNames = $teamIds->isNotEmpty()
            ? DB::table('teams')->whereIn('id', $teamIds->unique()->all())->pluck('name', 'id')
            : collect();
        $departmentNames = $departmentIds->isNotEmpty()
            ? DB::table('departments')->whereIn('id', $departmentIds->unique()->all())->pluck('name', 'id')
            : collect();

        // 3. Resolve values within old_values and new_values + add field_labels
        return $rows->map(function (array $row) use ($userNames, $categoryNames, $typeNames, $teamNames, $departmentNames) {
            foreach (['old_values', 'new_values'] as $key) {
                if (! is_array($row[$key] ?? null)) {
                    continue;
                }
                $row[$key] = $this->resolveObjectFields($row[$key], $userNames, $categoryNames, $typeNames, $teamNames, $departmentNames);
            }

            // Build field_labels map for this entry
            $labels = [];
            foreach (['old_values', 'new_values'] as $key) {
                if (! is_array($row[$key] ?? null)) {
                    continue;
                }
                foreach (array_keys($row[$key]) as $field) {
                    if (! isset($labels[$field])) {
                        $labels[$field] = $this->getFieldLabel($field);
                    }
                }
            }
            $row['field_labels'] = $labels;

            return $row;
        });
    }

    /**
     * Resolve IDs within a key-value object (old_values / new_values).
     */
    private function resolveObjectFields(
        array $obj,
        Collection $userNames,
        Collection $categoryNames,
        Collection $typeNames,
        Collection $teamNames,
        Collection $departmentNames
    ): array {
        foreach ($obj as $field => $val) {
            if ($val === null || $val === '') {
                continue;
            }
            if (in_array($field, self::$userIdFields, true)) {
                $obj[$field] = $this->resolveValue($val, $userNames);
            } elseif ($field === 'service_category_id') {
                $obj[$field] = $this->resolveValue($val, $categoryNames);
            } elseif ($field === 'service_type_id') {
                $obj[$field] = $this->resolveValue($val, $typeNames);
            } elseif ($field === 'team_id') {
                $obj[$field] = $this->resolveValue($val, $teamNames);
            } elseif ($field === 'department_id') {
                $obj[$field] = $this->resolveValue($val, $departmentNames);
            } elseif ($field === 'status') {
                $obj[$field] = $this->resolveStatusValue($val);
            }
        }

        return $obj;
    }
}
