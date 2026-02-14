<?php

namespace Database\Seeders;

use App\Models\SecuritySetting;
use Illuminate\Database\Seeder;

class SecuritySettingsSeeder extends Seeder
{
    public function run(): void
    {
        SecuritySetting::updateOrCreate(
            ['id' => 1],
            SecuritySetting::DEFAULTS
        );
    }
}
