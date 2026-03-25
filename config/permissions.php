<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CRM Modules (for permission matrix)
    |--------------------------------------------------------------------------
    | Key = module slug used in permission names
    | Value = label to show in UI
    */

    'modules' => [
        'lead-submissions' => 'Lead Submissions',
        'field-submissions' => 'Field Submissions',
        'vas_requests' => 'VAS Requests',
        'customer_support_requests' => 'Customer Support Requests',
        'special_requests' => 'Special Requests',
        'accounts' => 'All Clients',
        'clients' => 'Clients',
        'order_status' => 'Order Status',
        'dsp_tracker' => 'DSP Tracker',
        'attendance' => 'Attendance Log',
        'gsm_verifiers' => 'Verifiers Detail',
        'extensions' => 'Cisco Extensions',
        'expense_tracker' => 'Expense Tracker',
        'personal_notes' => 'Personal Notes',
        'emails_followup' => 'Email Follow-Up',
        'reports' => 'Reports',
        'notifications' => 'Notifications',
        'users' => 'Users',
        'teams' => 'Teams',
    ],

    /*
    |--------------------------------------------------------------------------
    | CRUD Actions
    |--------------------------------------------------------------------------
    | You asked: show, add, edit, listing, delete for ALL modules.
    | We'll map them to consistent action keys.
    */

    'actions' => [
        'list'   => 'Listing',
        'view'   => 'Show',
        'create' => 'Add',
        'edit'   => 'Edit',
        'delete' => 'Delete',
    ],

    /*
    |--------------------------------------------------------------------------
    | Canonical RBAC actions (system-wide enforcement contract)
    |--------------------------------------------------------------------------
    */
    'canonical_actions' => [
        'create',
        'read',
        'update',
        'delete',
        'assign_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Action alias map (canonical -> accepted legacy variants)
    |--------------------------------------------------------------------------
    */
    'action_aliases' => [
        'create' => ['create', 'add'],
        'read' => ['read', 'list', 'view'],
        'view' => ['view', 'list', 'read'],
        'update' => ['update', 'edit'],
        'edit' => ['edit', 'update'],
        'delete' => ['delete'],
        'assign_permissions' => ['assign_permissions', 'manage_permissions'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission name format
    |--------------------------------------------------------------------------
    | Result: {module}.{action}
    | Example: users.create, accounts.delete
    */

    'format' => '{module}.{action}',

    /*
    |--------------------------------------------------------------------------
    | Module structure for permissions UI (card layout with priority badges)
    |--------------------------------------------------------------------------
    | Each module has label, icon, and permissions array: key (suffix for name), label, priority (high|medium|low).
    | Permission name in DB = module_key.key (e.g. dashboard.view_dashboard).
    */

    'structure' => [
        'lead-submissions' => [
            'label' => 'Lead Submissions',
            'icon' => 'submissions',
            'permissions' => [
                ['key' => 'list', 'label' => 'Lead Submissions Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Lead Submissions', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Lead Submission', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Lead Submission', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Lead Submission', 'priority' => 'high'],
                ['key' => 'assign_bo_executive', 'label' => 'Assign Back Office Executive', 'priority' => 'low'],
                ['key' => 'resubmit_lead', 'label' => 'Resubmit Lead', 'priority' => 'low'],
                ['key' => 'bulk_assign', 'label' => 'Bulk Assign', 'priority' => 'medium'],
                ['key' => 'export', 'label' => 'Export Data', 'priority' => 'low'],
                ['key' => 'download_template', 'label' => 'Download Template', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'field-submissions' => [
            'label' => 'Field Submissions',
            'icon' => 'field_head',
            'permissions' => [
                ['key' => 'list', 'label' => 'Field Submissions Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Field Submissions', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Field Submission', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Field Submission', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Field Submission', 'priority' => 'high'],
                ['key' => 'assign_field_agent', 'label' => 'Assign Field Agent', 'priority' => 'low'],
                ['key' => 'change_meeting_status', 'label' => 'Change Meeting Status', 'priority' => 'low'],
                ['key' => 'upload_field_proof', 'label' => 'Upload Field Proof', 'priority' => 'low'],
                ['key' => 'bulk_assign', 'label' => 'Bulk Assign', 'priority' => 'medium'],
                ['key' => 'export', 'label' => 'Export Data', 'priority' => 'low'],
                ['key' => 'download_template', 'label' => 'Download Template', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'vas_requests' => [
            'label' => 'VAS Requests',
            'icon' => 'vas_requests',
            'permissions' => [
                ['key' => 'list', 'label' => 'VAS Requests Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View VAS Requests', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create VAS Request', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit VAS Request', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete VAS Request', 'priority' => 'high'],
                ['key' => 'process_vas_requests', 'label' => 'Process VAS Requests', 'priority' => 'low'],
                ['key' => 'change_du_status', 'label' => 'Change DU Status', 'priority' => 'low'],
                ['key' => 'add_remarks', 'label' => 'Add Remarks', 'priority' => 'medium'],
                ['key' => 'assign_bo_executive', 'label' => 'Assign Back Office Executive', 'priority' => 'low'],
                ['key' => 'bulk_assign', 'label' => 'Bulk Assign', 'priority' => 'medium'],
                ['key' => 'export', 'label' => 'Export Data', 'priority' => 'low'],
                ['key' => 'download_template', 'label' => 'Download Template', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'customer_support_requests' => [
            'label' => 'Customer Support Requests',
            'icon' => 'customer_support',
            'permissions' => [
                ['key' => 'list', 'label' => 'Customer Support Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Customer Support Requests', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Customer Support Request', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Customer Support Request', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Customer Support Request', 'priority' => 'high'],
                ['key' => 'assign_csr', 'label' => 'Assign CSR', 'priority' => 'low'],
                ['key' => 'change_ticket_status', 'label' => 'Change Ticket Status', 'priority' => 'low'],
                ['key' => 'add_resolution_remarks', 'label' => 'Add Resolution Remarks', 'priority' => 'medium'],
                ['key' => 'export_tickets', 'label' => 'Export Tickets', 'priority' => 'low'],
                ['key' => 'bulk_assign', 'label' => 'Bulk Assign', 'priority' => 'medium'],
                ['key' => 'export', 'label' => 'Export Data', 'priority' => 'low'],
                ['key' => 'download_template', 'label' => 'Download Template', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'special_requests' => [
            'label' => 'Special Requests',
            'icon' => 'submissions',
            'permissions' => [
                ['key' => 'list', 'label' => 'Special Requests Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Special Requests', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Special Request', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Special Request', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Special Request', 'priority' => 'high'],
                ['key' => 'export', 'label' => 'Export Data', 'priority' => 'low'],
                ['key' => 'download_template', 'label' => 'Download Template', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'accounts' => [
            'label' => 'All Clients',
            'icon' => 'clients',
            'permissions' => [
                ['key' => 'list', 'label' => 'All Clients Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View All Clients', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Client', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Client', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Client', 'priority' => 'high'],
                ['key' => 'search_clients', 'label' => 'Search Clients', 'priority' => 'low'],
                ['key' => 'add_edit_products', 'label' => 'Add/Edit Products & Services', 'priority' => 'medium'],
                ['key' => 'export_client_data', 'label' => 'Export Client Data', 'priority' => 'low'],
                ['key' => 'import', 'label' => 'Import Data', 'priority' => 'medium'],
                ['key' => 'download_template', 'label' => 'Download Template', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'clients' => [
            'label' => 'Clients',
            'icon' => 'clients',
            'permissions' => [
                ['key' => 'list', 'label' => 'Clients Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Client', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Client', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Client', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Client', 'priority' => 'high'],
                ['key' => 'export', 'label' => 'Export Data', 'priority' => 'low'],
                ['key' => 'import', 'label' => 'Import Data', 'priority' => 'medium'],
                ['key' => 'download_template', 'label' => 'Download Template', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'order_status' => [
            'label' => 'Order Status',
            'icon' => 'order_status',
            'permissions' => [
                ['key' => 'list', 'label' => 'Order Status Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Order Status', 'priority' => 'low'],
                ['key' => 'search_by_activity', 'label' => 'Search by Activity', 'priority' => 'low'],
                ['key' => 'search_by_account_number', 'label' => 'Search by Account Number', 'priority' => 'low'],
                ['key' => 'search_by_work_order', 'label' => 'Search by Work Order', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'dsp_tracker' => [
            'label' => 'DSP Tracker',
            'icon' => 'dsp_tracker',
            'permissions' => [
                ['key' => 'list', 'label' => 'DSP Tracker Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View DSP Tracker', 'priority' => 'low'],
                ['key' => 'upload_csv', 'label' => 'Upload CSV', 'priority' => 'low'],
                ['key' => 'delete_existing_csv', 'label' => 'Delete Existing CSV', 'priority' => 'high'],
                ['key' => 'search_dsp_status', 'label' => 'Search DSP Status', 'priority' => 'low'],
                ['key' => 'export_dsp_data', 'label' => 'Export DSP Data', 'priority' => 'low'],
                ['key' => 'download_template', 'label' => 'Download Template', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'attendance' => [
            'label' => 'Attendance Log',
            'icon' => 'attendance',
            'permissions' => [
                ['key' => 'list', 'label' => 'Attendance Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Attendance Log', 'priority' => 'low'],
                ['key' => 'edit', 'label' => 'Force Logout (Edit)', 'priority' => 'medium'],
                ['key' => 'export', 'label' => 'Export Attendance Data', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'gsm_verifiers' => [
            'label' => 'Verifiers Detail',
            'icon' => 'verifiers',
            'permissions' => [
                ['key' => 'list', 'label' => 'Verifiers Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Verifier', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Verifier', 'priority' => 'medium'],
                ['key' => 'add_verifier', 'label' => 'Add Verifier', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Verifier', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Verifier', 'priority' => 'high'],
                ['key' => 'import', 'label' => 'Import Verifiers', 'priority' => 'medium'],
                ['key' => 'export', 'label' => 'Export Verifiers', 'priority' => 'low'],
            ],
        ],

        'extensions' => [
            'label' => 'Cisco Extensions',
            'icon' => 'extensions',
            'permissions' => [
                ['key' => 'list', 'label' => 'Extensions Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Extension', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Extension', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Extension', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Extension', 'priority' => 'high'],
                ['key' => 'assign_extension', 'label' => 'Assign Extension', 'priority' => 'low'],
                ['key' => 'bulk_upload_extensions', 'label' => 'Bulk Upload Extensions', 'priority' => 'low'],
                ['key' => 'bulk_download_extensions', 'label' => 'Bulk Download Extensions', 'priority' => 'low'],
                ['key' => 'export', 'label' => 'Export Data', 'priority' => 'low'],
                ['key' => 'import', 'label' => 'Import Data', 'priority' => 'medium'],
                ['key' => 'download_template', 'label' => 'Download Template', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'expense_tracker' => [
            'label' => 'Expense Tracker',
            'icon' => 'expense_tracker',
            'permissions' => [
                ['key' => 'list', 'label' => 'Expense Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Expense', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Expense', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Expense', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Expense', 'priority' => 'high'],
                ['key' => 'export_expenses', 'label' => 'Export Expenses', 'priority' => 'low'],
                ['key' => 'download_template', 'label' => 'Download Template', 'priority' => 'low'],
                ['key' => 'import', 'label' => 'Import Data', 'priority' => 'medium'],
                ['key' => 'export', 'label' => 'Export Data', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'personal_notes' => [
            'label' => 'Personal Notes',
            'icon' => 'personal_notes',
            'permissions' => [
                ['key' => 'list', 'label' => 'Notes Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Note', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Note', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Note', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Note', 'priority' => 'high'],
            ],
        ],

        'emails_followup' => [
            'label' => 'Email Follow-Up',
            'icon' => 'email_followup',
            'permissions' => [
                ['key' => 'list', 'label' => 'Email Follow-Up Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Email Follow-Up', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Email Follow-Up', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Email Follow-Up', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Email Follow-Up', 'priority' => 'high'],
                ['key' => 'export_email_data', 'label' => 'Export Email Follow-Up Data', 'priority' => 'low'],
                ['key' => 'export', 'label' => 'Export Data', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],

        'reports' => [
            'label' => 'Reports',
            'icon' => 'reports',
            'permissions' => [
                ['key' => 'list', 'label' => 'Reports Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Reports', 'priority' => 'low'],
                ['key' => 'view_sla_reports', 'label' => 'View SLA Reports', 'priority' => 'low'],
                ['key' => 'view_vas_reports', 'label' => 'View VAS Reports', 'priority' => 'low'],
                ['key' => 'export_reports', 'label' => 'Export Reports', 'priority' => 'low'],
            ],
        ],

        'notifications' => [
            'label' => 'Notifications',
            'icon' => 'notification_rules',
            'permissions' => [
                ['key' => 'list', 'label' => 'Notifications Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Notifications', 'priority' => 'low'],
                ['key' => 'edit', 'label' => 'Update Notification State', 'priority' => 'medium'],
            ],
        ],

        'users' => [
            'label' => 'Users',
            'icon' => 'employees',
            'permissions' => [
                ['key' => 'list', 'label' => 'Users Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View User', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create User', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit User', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete User', 'priority' => 'high'],
                ['key' => 'assign_extensions', 'label' => 'Assign Extensions', 'priority' => 'low'],
                ['key' => 'bulk_upload_employees', 'label' => 'Bulk Upload Users', 'priority' => 'low'],
                ['key' => 'export', 'label' => 'Export Users', 'priority' => 'low'],
                ['key' => 'import', 'label' => 'Import Users', 'priority' => 'medium'],
            ],
        ],

        'teams' => [
            'label' => 'Teams',
            'icon' => 'team',
            'permissions' => [
                ['key' => 'list', 'label' => 'Teams Listing', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Team', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Team', 'priority' => 'medium'],
                ['key' => 'edit', 'label' => 'Edit Team', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Team', 'priority' => 'high'],
                ['key' => 'manage_members', 'label' => 'Manage Team Members', 'priority' => 'low'],
                ['key' => 'import', 'label' => 'Import Data', 'priority' => 'medium'],
                ['key' => 'export', 'label' => 'Export Data', 'priority' => 'low'],
                ['key' => 'download_template', 'label' => 'Download Template', 'priority' => 'low'],
                ['key' => 'apply_filters', 'label' => 'Apply Filters', 'priority' => 'low'],
                ['key' => 'reset_filters', 'label' => 'Reset Filters', 'priority' => 'low'],
                ['key' => 'advanced_filters', 'label' => 'Advanced Filters', 'priority' => 'low'],
                ['key' => 'customize_columns', 'label' => 'Customize Columns', 'priority' => 'low'],
            ],
        ],
    ],
];
