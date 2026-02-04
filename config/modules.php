<?php

return [

    'users' => [
        'model' => App\Models\User::class,

        'columns' => [
            'id' => [
                'label' => 'ID',
                'filter' => null,
                'sortable' => true,
            ],
            'name' => [
                'label' => 'Name',
                'filter' => 'text',
                'sortable' => true,
            ],
            'email' => [
                'label' => 'Email',
                'filter' => 'text',
                'sortable' => true,
            ],
            'status' => [
                'label' => 'Status',
                'filter' => 'select',
                'options' => ['active', 'inactive'],
                'sortable' => true,
            ],
            'created_at' => [
                'label' => 'Created At',
                'filter' => 'date',
                'sortable' => true,
            ],
        ],

        'default_columns' => ['id', 'name', 'email', 'status'],
        'default_sort' => ['created_at', 'desc'],
    ],

    'default_columns' => [
        'admin' => ['id', 'name', 'email', 'status'],
        'manager' => ['name', 'email'],
        'user' => ['name']
    ],

    'lead_submissions' => [
        'model' => \App\Models\LeadSubmission::class,
        'columns' => [
            'id' => ['label' => 'ID', 'filter' => null, 'sortable' => true],
            'submitted_at' => ['label' => 'Submission Date', 'filter' => 'date', 'sortable' => true],
            'account_number' => ['label' => 'Account Number', 'filter' => 'text', 'sortable' => true],
            'company_name' => ['label' => 'Company Name', 'filter' => 'text', 'sortable' => true],
            'category' => ['label' => 'Service Category', 'filter' => 'select', 'sortable' => true],
            'type' => ['label' => 'Service Type', 'filter' => 'select', 'sortable' => true],
            'product' => ['label' => 'Product', 'filter' => 'text', 'sortable' => true],
            'mrc_aed' => ['label' => 'MRC (AED)', 'filter' => null, 'sortable' => true],
            'quantity' => ['label' => 'Qty', 'filter' => null, 'sortable' => true],
            'sales_agent' => ['label' => 'Sales Agent', 'filter' => null, 'sortable' => true],
            'team_leader' => ['label' => 'Team Leader', 'filter' => null, 'sortable' => true],
            'manager' => ['label' => 'Manager', 'filter' => null, 'sortable' => true],
            'status' => ['label' => 'Status', 'filter' => 'select', 'sortable' => true],
            'status_changed_at' => ['label' => 'Last Updated', 'filter' => 'date', 'sortable' => true],
            'created_at' => ['label' => 'Created', 'filter' => 'date', 'sortable' => true],
            'creator' => ['label' => 'Created By', 'filter' => null, 'sortable' => false],
            'email' => ['label' => 'Email', 'filter' => 'text', 'sortable' => true],
            'contact_number_gsm' => ['label' => 'Contact', 'filter' => 'text', 'sortable' => false],
        ],
        'default_columns' => ['submitted_at', 'created_at', 'account_number', 'company_name', 'category', 'type', 'product', 'mrc_aed', 'quantity', 'manager', 'team_leader', 'sales_agent', 'creator', 'email', 'contact_number_gsm', 'status', 'status_changed_at'],
        'default_sort' => ['created_at', 'desc'],
    ],

    'field_submissions' => [
        'model' => \App\Models\FieldSubmission::class,
        'columns' => [
            'id' => ['label' => 'ID', 'filter' => null, 'sortable' => true],
            'submitted_at' => ['label' => 'Submission Date', 'filter' => 'date', 'sortable' => true],
            'created_at' => ['label' => 'Created', 'filter' => 'date', 'sortable' => true],
            'company_name' => ['label' => 'Company Name', 'filter' => 'text', 'sortable' => true],
            'contact_number' => ['label' => 'Contact Number', 'filter' => 'text', 'sortable' => true],
            'product' => ['label' => 'Product', 'filter' => 'text', 'sortable' => true],
            'emirates' => ['label' => 'Emirates', 'filter' => 'text', 'sortable' => true],
            'complete_address' => ['label' => 'Address', 'filter' => null, 'sortable' => false],
            'sales_agent' => ['label' => 'Sales Agent', 'filter' => null, 'sortable' => true],
            'team_leader' => ['label' => 'Team Leader', 'filter' => null, 'sortable' => true],
            'manager' => ['label' => 'Manager', 'filter' => null, 'sortable' => true],
            'field_agent' => ['label' => 'Field Agent', 'filter' => null, 'sortable' => true],
            'status' => ['label' => 'Status', 'filter' => 'select', 'sortable' => true],
            'field_status' => ['label' => 'Status', 'filter' => 'select', 'sortable' => true],
            'target_date' => ['label' => 'Target Date', 'filter' => 'date', 'sortable' => true],
            'sla_timer' => ['label' => 'SLA Timer', 'filter' => null, 'sortable' => true],
            'sla_status' => ['label' => 'SLA Status', 'filter' => null, 'sortable' => true],
            'last_updated' => ['label' => 'Last Updated', 'filter' => 'date', 'sortable' => true],
            'creator' => ['label' => 'Created By', 'filter' => null, 'sortable' => false],
        ],
        'default_columns' => ['submitted_at', 'company_name', 'emirates', 'product', 'sales_agent', 'team_leader', 'manager', 'field_agent', 'field_status', 'target_date', 'sla_timer', 'sla_status', 'last_updated'],
        'default_sort' => ['created_at', 'desc'],
    ],

];
