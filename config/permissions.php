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
];
