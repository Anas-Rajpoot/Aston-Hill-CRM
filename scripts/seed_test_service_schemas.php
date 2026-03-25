<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$schemas = [
    1 => [
        'fields' => [
            [
                'key' => 'connection_speed',
                'label' => 'Connection Speed',
                'type' => 'select',
                'required' => true,
                'options' => ['50 Mbps', '100 Mbps', '250 Mbps', '500 Mbps'],
            ],
            [
                'key' => 'contract_months',
                'label' => 'Contract (Months)',
                'type' => 'number',
                'required' => true,
                'max' => 36,
            ],
            [
                'key' => 'installation_date',
                'label' => 'Preferred Installation Date',
                'type' => 'date',
                'required' => false,
            ],
            [
                'key' => 'technical_notes',
                'label' => 'Technical Notes',
                'type' => 'textarea',
                'required' => false,
                'max' => 500,
            ],
        ],
        'documents' => [
            ['key' => 'trade_license', 'label' => 'Trade License', 'required' => true],
            ['key' => 'owner_emirates_id', 'label' => 'Owner Emirates ID', 'required' => true],
            ['key' => 'ejari', 'label' => 'Ejari', 'required' => false],
            ['key' => 'proposal_form', 'label' => 'Proposal Form', 'required' => false],
        ],
    ],
    10 => [
        'fields' => [
            [
                'key' => 'sim_plan',
                'label' => 'SIM Plan',
                'type' => 'select',
                'required' => true,
                'options' => ['Postpaid 125', 'Postpaid 200', 'Postpaid 350'],
            ],
            [
                'key' => 'sim_quantity',
                'label' => 'SIM Quantity',
                'type' => 'number',
                'required' => true,
                'max' => 500,
            ],
            [
                'key' => 'porting_required',
                'label' => 'Porting Required',
                'type' => 'checkbox',
                'required' => false,
            ],
        ],
        'documents' => [
            ['key' => 'trade_license', 'label' => 'Trade License', 'required' => true],
            ['key' => 'authorized_signatory_eid', 'label' => 'Authorized Signatory EID', 'required' => true],
            ['key' => 'sim_request_form', 'label' => 'SIM Request Form', 'required' => false],
        ],
    ],
];

foreach ($schemas as $typeId => $schema) {
    $type = App\Models\ServiceType::query()->find($typeId);
    if (! $type) {
        echo "Skipped type {$typeId}: not found." . PHP_EOL;
        continue;
    }

    $type->schema = $schema;
    $type->save();

    echo "Updated type {$typeId}: {$type->name}" . PHP_EOL;
}

echo "Done." . PHP_EOL;

