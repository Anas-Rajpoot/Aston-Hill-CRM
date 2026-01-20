<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $path = storage_path('app/data/countries.json');
        $path = database_path('data/countries.json');
        $countries = json_decode(file_get_contents($path), true);
        
        $now = now();
        $rows = [];

        foreach ($countries as $country) {
            $code = strtoupper($country['cca2'] ?? '');

            if (!$code) continue;

            $name = $country['name']['common']
                ?? $country['name']['official']
                ?? null;

            if (!$name) continue;

            $timezone = $country['timezones'][0] ?? null;

            $rows[] = [
                'code'       => $code,
                'name'       => $name,
                'timezone'   => $timezone,
                'is_active'  => true,
                'created_at'=> $now,
                'updated_at'=> $now,
            ];

            Country::upsert(
                $rows,
                ['code'],
                ['name', 'timezone', 'is_active', 'updated_at']
            );
        }
    }
}
