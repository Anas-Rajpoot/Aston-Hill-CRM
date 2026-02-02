<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Fixed',
                'slug' => 'fixed',
                'sort_order' => 1,
                'types' => ['New Submission', 'Relocation', 'Update WO', 'Contract Renewal', 'Migration', 'Offer'],
            ],
            [
                'name' => 'FMS',
                'slug' => 'fms',
                'sort_order' => 2,
                'types' => ['New Submission', 'Relocation', 'Contract Renewal'],
            ],
            [
                'name' => 'GSM',
                'slug' => 'gsm',
                'sort_order' => 3,
                'types' => ['New SIM Card', 'MNP', 'Migration', 'SIM Replacement'],
            ],
            [
                'name' => 'Other',
                'slug' => 'other',
                'sort_order' => 4,
                'types' => ['Office 365', 'Number Swapping', 'Other Request', 'Domain Activation', 'Device Request'],
            ],
        ];

        foreach ($categories as $i => $catData) {
            $types = $catData['types'];
            unset($catData['types']);

            $category = ServiceCategory::updateOrCreate(
                ['slug' => $catData['slug']],
                $catData
            );

            foreach ($types as $j => $typeName) {
                $slug = str($typeName)->slug()->toString() . '-' . $category->slug;
                ServiceType::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'service_category_id' => $category->id,
                        'name' => $typeName,
                        'sort_order' => $j + 1,
                        'schema' => ['fields' => [], 'documents' => []],
                    ]
                );
            }
        }
    }
}
