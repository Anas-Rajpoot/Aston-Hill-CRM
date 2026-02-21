<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $submissions = DB::table('customer_support_submissions')
            ->whereNull('ticket_number')
            ->orWhere('ticket_number', '')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'created_at']);

        $dayCounts = [];

        foreach ($submissions as $row) {
            $date = \Carbon\Carbon::parse($row->created_at);
            $dateKey = $date->format('Y-m-d');
            $prefix = 'AH' . $date->format('dmY');

            if (! isset($dayCounts[$dateKey])) {
                $dayCounts[$dateKey] = DB::table('customer_support_submissions')
                    ->whereDate('created_at', $dateKey)
                    ->whereNotNull('ticket_number')
                    ->where('ticket_number', '!=', '')
                    ->count();
            }

            $dayCounts[$dateKey]++;
            $ticket = $prefix . str_pad($dayCounts[$dateKey], 2, '0', STR_PAD_LEFT);

            DB::table('customer_support_submissions')
                ->where('id', $row->id)
                ->update(['ticket_number' => $ticket]);
        }
    }

    public function down(): void
    {
        // No rollback needed
    }
};
