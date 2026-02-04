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
        // Core
        'roles' => 'Roles',
        'users' => 'Users',
        'extensions' => 'Extensions',
        'sales_agents' => 'Sales Agents',
        'attendance' => 'Attendance',

        // Submissions
        'submissions' => 'My Submissions',
        'submission_details' => 'Submission Details',
        'lead-submissions' => 'Lead Submissions',
        'field-submissions' => 'Field Submissions',
        'vas_requests' => 'VAS Requests',

        // Customer Support
        'customer_support_requests' => 'Customer Support Requests',
        'my_customer_support_requests' => 'My Customer Support Requests',

        // DSP Tracker
        'dsp_tracker_upload' => 'DSP Tracker Upload',
        'dsp_tracker_status' => 'DSP Tracker Status',

        // GSM / Orders
        'gsm_verifiers' => 'GSM Verifiers',
        'order_status' => 'Order Status',

        // Accounts / Companies
        'accounts' => 'Accounts',
        'company_details' => 'Company Details',
        'assign_csrs' => 'Assign/Unassign CSRs',
        'authorized_signatories' => 'Authorized Signatories',
        'products' => 'Products',
        'customer_support_vas' => 'Customer Support / VAS (From Forms)',

        // Communications
        'notifications' => 'Personalized Notifications',
        'alerts' => 'Specific Person Alerts (SLA, etc.)',
        'emails_followup' => 'Emails Follow up',
        'announcements' => 'Announcements Center',

        // Reports / Tools
        'expense_tracker' => 'Expense Tracker',
        'commission_calculator' => 'Commission Calculator',
        'personal_notes' => 'Personal Notes',
        'lead_tracker' => 'Lead Tracker',
        'reports' => 'Reports',
        'settings' => 'Settings',
        'data_import_export' => 'Data Import / Export',
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
        'dashboard' => [
            'label' => 'Dashboard',
            'icon' => 'dashboard',
            'permissions' => [
                ['key' => 'view_dashboard', 'label' => 'View Dashboard', 'priority' => 'high'],
                ['key' => 'view_pending_tasks', 'label' => 'View Pending Tasks', 'priority' => 'medium'],
                ['key' => 'view_all_kpis', 'label' => 'View All KPIs', 'priority' => 'high'],
                ['key' => 'view_sla_alerts', 'label' => 'View SLA Alerts', 'priority' => 'medium'],
            ],
        ],
        'lead' => [
            'label' => 'Lead Submissions (Listing)',
            'icon' => 'submissions',
            'permissions' => [
                ['key' => 'view', 'label' => 'View Leads', 'priority' => 'high'],
                ['key' => 'view.all', 'label' => 'View All Leads', 'priority' => 'high'],
                ['key' => 'view.assigned', 'label' => 'View Assigned Leads', 'priority' => 'high'],
                ['key' => 'view.created', 'label' => 'View Created Leads', 'priority' => 'high'],
                ['key' => 'create', 'label' => 'Create Lead', 'priority' => 'high'],
                ['key' => 'edit', 'label' => 'Edit Lead', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Lead', 'priority' => 'medium'],
            ],
        ],
        'lead-submissions' => [
            'label' => 'Submissions (Main Hub)',
            'icon' => 'submissions',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Submissions Page', 'priority' => 'high'],
                ['key' => 'create', 'label' => 'Create Lead Submission', 'priority' => 'high'],
                ['key' => 'resubmit_lead', 'label' => 'Resubmit Lead', 'priority' => 'medium'],
                ['key' => 'create_field_submission', 'label' => 'Create Field Submission', 'priority' => 'medium'],
                ['key' => 'create_customer_support_request', 'label' => 'Create Customer Support Request', 'priority' => 'low'],
                ['key' => 'create_vas_request', 'label' => 'Create VAS Request', 'priority' => 'low'],
                ['key' => 'view', 'label' => 'View Lead Submissions', 'priority' => 'high'],
                ['key' => 'edit', 'label' => 'Edit Lead Submission', 'priority' => 'medium'],
                ['key' => 'view_field_submissions', 'label' => 'View Field Submissions', 'priority' => 'medium'],
                ['key' => 'view_customer_support_submissions', 'label' => 'View Customer Support Submissions', 'priority' => 'low'],
                ['key' => 'view_vas_requests', 'label' => 'View VAS Requests', 'priority' => 'low'],
            ],
        ],
        'back_office' => [
            'label' => 'Back Office',
            'icon' => 'back_office',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Back Office Page', 'priority' => 'high'],
                ['key' => 'view', 'label' => 'View All Submissions', 'priority' => 'high'],
                ['key' => 'assign_bo_executive', 'label' => 'Assign Back Office Executive', 'priority' => 'high'],
                ['key' => 'verify_documents', 'label' => 'Verify Documents', 'priority' => 'high'],
                ['key' => 'verify_calls', 'label' => 'Verify Calls', 'priority' => 'high'],
                ['key' => 'change_status', 'label' => 'Change Status', 'priority' => 'high'],
                ['key' => 'add_bo_remarks', 'label' => 'Add BO Remarks', 'priority' => 'medium'],
                ['key' => 'reject_submission', 'label' => 'Reject Submission', 'priority' => 'high'],
                ['key' => 'bulk_download', 'label' => 'Bulk Download', 'priority' => 'low'],
                ['key' => 'export_data', 'label' => 'Export Data', 'priority' => 'low'],
                ['key' => 'use_advanced_filters', 'label' => 'Use Advanced Filters', 'priority' => 'medium'],
            ],
        ],
        'field_head' => [
            'label' => 'Field Head',
            'icon' => 'field_head',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Field Head Page', 'priority' => 'high'],
                ['key' => 'view', 'label' => 'View Field Submissions', 'priority' => 'high'],
                ['key' => 'assign_field_agent', 'label' => 'Assign Field Agent', 'priority' => 'high'],
                ['key' => 'change_meeting_status', 'label' => 'Change Meeting Status', 'priority' => 'high'],
                ['key' => 'upload_field_proof', 'label' => 'Upload Field Proof', 'priority' => 'medium'],
                ['key' => 'view_sla_timers', 'label' => 'View SLA Timers', 'priority' => 'medium'],
            ],
        ],
        'customer_support_requests' => [
            'label' => 'Customer Support',
            'icon' => 'customer_support',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Customer Support Page', 'priority' => 'high'],
                ['key' => 'create', 'label' => 'Create Ticket', 'priority' => 'high'],
                ['key' => 'view', 'label' => 'View All Tickets', 'priority' => 'high'],
                ['key' => 'assign_csr', 'label' => 'Assign CSR', 'priority' => 'high'],
                ['key' => 'change_ticket_status', 'label' => 'Change Ticket Status', 'priority' => 'high'],
                ['key' => 'add_resolution_remarks', 'label' => 'Add Resolution Remarks', 'priority' => 'medium'],
                ['key' => 'export_tickets', 'label' => 'Export Tickets', 'priority' => 'low'],
            ],
        ],
        'vas_requests' => [
            'label' => 'VAS Requests',
            'icon' => 'vas_requests',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access VAS Requests Page', 'priority' => 'high'],
                ['key' => 'view', 'label' => 'View Requests', 'priority' => 'high'],
                ['key' => 'process_vas_requests', 'label' => 'Process VAS Requests', 'priority' => 'high'],
                ['key' => 'change_du_status', 'label' => 'Change DU Status', 'priority' => 'high'],
                ['key' => 'add_remarks', 'label' => 'Add Remarks', 'priority' => 'medium'],
                ['key' => 'export_vas_data', 'label' => 'Export VAS Data', 'priority' => 'low'],
            ],
        ],
        'accounts' => [
            'label' => 'Clients',
            'icon' => 'clients',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Clients Module', 'priority' => 'high'],
                ['key' => 'view', 'label' => 'View Client Profile', 'priority' => 'high'],
                ['key' => 'search_clients', 'label' => 'Search Clients', 'priority' => 'high'],
                ['key' => 'edit', 'label' => 'Edit Client Details', 'priority' => 'medium'],
                ['key' => 'add_edit_products', 'label' => 'Add / Edit Products', 'priority' => 'medium'],
                ['key' => 'view_support_history', 'label' => 'View Support History', 'priority' => 'low'],
                ['key' => 'view_vas_history', 'label' => 'View VAS History', 'priority' => 'low'],
                ['key' => 'view_alerts', 'label' => 'View Alerts', 'priority' => 'medium'],
                ['key' => 'export_client_data', 'label' => 'Export Client Data', 'priority' => 'low'],
            ],
        ],
        'order_status' => [
            'label' => 'Order Status',
            'icon' => 'order_status',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Order Status Page', 'priority' => 'high'],
                ['key' => 'search_by_activity', 'label' => 'Search by Activity', 'priority' => 'high'],
                ['key' => 'search_by_account_number', 'label' => 'Search by Account Number', 'priority' => 'high'],
                ['key' => 'search_by_work_order', 'label' => 'Search by Work Order', 'priority' => 'high'],
            ],
        ],
        'gsm_tracker' => [
            'label' => 'GSM Tracker',
            'icon' => 'gsm_tracker',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access GSM Tracker', 'priority' => 'medium'],
                ['key' => 'check_status', 'label' => 'Check Status', 'priority' => 'medium'],
                ['key' => 'export_gsm_data', 'label' => 'Export GSM Data', 'priority' => 'low'],
            ],
        ],
        'dsp_tracker' => [
            'label' => 'DSP Tracker',
            'icon' => 'dsp_tracker',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access DSP Tracker', 'priority' => 'medium'],
                ['key' => 'upload_csv', 'label' => 'Upload CSV', 'priority' => 'high'],
                ['key' => 'delete_existing_csv', 'label' => 'Delete Existing CSV', 'priority' => 'high'],
                ['key' => 'search_dsp_status', 'label' => 'Search DSP Status', 'priority' => 'medium'],
                ['key' => 'export_dsp_data', 'label' => 'Export DSP Data', 'priority' => 'low'],
            ],
        ],
        'gsm_verifiers' => [
            'label' => 'Verifiers Detail',
            'icon' => 'verifiers',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Verifiers Page', 'priority' => 'medium'],
                ['key' => 'add_verifier', 'label' => 'Add Verifier', 'priority' => 'high'],
                ['key' => 'edit', 'label' => 'Edit Verifier', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Verifier', 'priority' => 'high'],
            ],
        ],
        'extensions' => [
            'label' => 'Cisco Extensions',
            'icon' => 'extensions',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Cisco Extensions', 'priority' => 'medium'],
                ['key' => 'create', 'label' => 'Add Extension', 'priority' => 'high'],
                ['key' => 'edit', 'label' => 'Edit Extension', 'priority' => 'medium'],
                ['key' => 'assign_extension', 'label' => 'Assign Extension', 'priority' => 'high'],
                ['key' => 'bulk_upload_extensions', 'label' => 'Bulk Upload Extensions', 'priority' => 'medium'],
                ['key' => 'bulk_download_extensions', 'label' => 'Bulk Download Extensions', 'priority' => 'low'],
            ],
        ],
        'emails_followup' => [
            'label' => 'Email Follow-Up',
            'icon' => 'email_followup',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Email Follow-Up', 'priority' => 'medium'],
                ['key' => 'create', 'label' => 'Add Email Entry', 'priority' => 'high'],
                ['key' => 'edit', 'label' => 'Edit Email Entry', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Email Entry', 'priority' => 'medium'],
                ['key' => 'export_email_data', 'label' => 'Export Email Data', 'priority' => 'low'],
            ],
        ],
        'expense_tracker' => [
            'label' => 'Expense Tracker',
            'icon' => 'expense_tracker',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Expense Tracker', 'priority' => 'medium'],
                ['key' => 'view', 'label' => 'View Expense', 'priority' => 'medium'],
                ['key' => 'create', 'label' => 'Add Expense', 'priority' => 'high'],
                ['key' => 'edit', 'label' => 'Edit Expense', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Delete Expense', 'priority' => 'medium'],
                ['key' => 'export_expenses', 'label' => 'Export Expenses', 'priority' => 'low'],
                ['key' => 'export', 'label' => 'Export (single/CSV)', 'priority' => 'low'],
                ['key' => 'update', 'label' => 'Update Expense', 'priority' => 'medium'],
            ],
        ],
        'attendance' => [
            'label' => 'Attendance Log',
            'icon' => 'attendance',
            'permissions' => [
                ['key' => 'view_attendance_logs', 'label' => 'View Attendance Logs (Super Admin only)', 'priority' => 'high'],
                ['key' => 'export_attendance_data', 'label' => 'Export Attendance Data', 'priority' => 'low'],
            ],
        ],
        'personal_notes' => [
            'label' => 'Personal Notes',
            'icon' => 'personal_notes',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Personal Notes', 'priority' => 'low'],
                ['key' => 'create', 'label' => 'Create Notes', 'priority' => 'low'],
                ['key' => 'edit', 'label' => 'Edit Notes', 'priority' => 'low'],
                ['key' => 'delete', 'label' => 'Delete Notes', 'priority' => 'low'],
            ],
        ],
        'users' => [
            'label' => 'Employees',
            'icon' => 'employees',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Employees Page', 'priority' => 'high'],
                ['key' => 'create', 'label' => 'Add Employee', 'priority' => 'high'],
                ['key' => 'edit', 'label' => 'Edit Employee', 'priority' => 'medium'],
                ['key' => 'delete', 'label' => 'Deactivate Employee', 'priority' => 'high'],
                ['key' => 'assign_extensions', 'label' => 'Assign Extensions', 'priority' => 'medium'],
                ['key' => 'bulk_upload_employees', 'label' => 'Bulk Upload Employees', 'priority' => 'medium'],
            ],
        ],
        'reports' => [
            'label' => 'Reports',
            'icon' => 'reports',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Reports', 'priority' => 'high'],
                ['key' => 'view_sla_reports', 'label' => 'View SLA Reports', 'priority' => 'medium'],
                ['key' => 'view_vas_reports', 'label' => 'View VAS Reports', 'priority' => 'medium'],
                ['key' => 'export_reports', 'label' => 'Export Reports', 'priority' => 'low'],
            ],
        ],
        'roles' => [
            'label' => 'Roles',
            'icon' => 'roles',
            'permissions' => [
                ['key' => 'list', 'label' => 'Access Roles', 'priority' => 'high'],
                ['key' => 'view', 'label' => 'View Role', 'priority' => 'high'],
                ['key' => 'create', 'label' => 'Add Role', 'priority' => 'high'],
                ['key' => 'edit', 'label' => 'Edit Role', 'priority' => 'high'],
                ['key' => 'delete', 'label' => 'Delete Role', 'priority' => 'high'],
                ['key' => 'manage_permissions', 'label' => 'Manage Permissions', 'priority' => 'high'],
            ],
        ],
    ],
];
