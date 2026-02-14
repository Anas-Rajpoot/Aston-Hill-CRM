<?php

namespace Database\Seeders;

use App\Models\SystemPreference;
use Illuminate\Database\Seeder;

class SystemPreferencesSeeder extends Seeder
{
    public function run(): void
    {
        SystemPreference::firstOrCreate(['id' => 1], SystemPreference::DEFAULTS);
    }
}
