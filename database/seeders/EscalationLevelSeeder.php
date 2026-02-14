<?php

namespace Database\Seeders;

use App\Models\EscalationLevel;
use Illuminate\Database\Seeder;

class EscalationLevelSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = EscalationLevel::defaults();

        foreach ($defaults as $row) {
            EscalationLevel::updateOrCreate(
                ['level' => $row['level']],
                $row,
            );
        }
    }
}
