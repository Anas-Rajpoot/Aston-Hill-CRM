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

];
