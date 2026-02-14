<?php

namespace Database\Seeders;

use App\Models\SlaRule;
use Illuminate\Database\Seeder;

class SlaRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['module_key' => 'lead_submissions',         'module_name' => 'Lead Submissions',                  'sla_duration_minutes' => 480,  'warning_threshold_minutes' => 60,  'notification_email' => 'order@astonhill.ae'],
            ['module_key' => 'lead_resubmissions',       'module_name' => 'Lead Resubmissions',                'sla_duration_minutes' => 360,  'warning_threshold_minutes' => 45,  'notification_email' => 'order@astonhill.ae'],
            ['module_key' => 'back_office_queue',        'module_name' => 'Back Office Queue',                 'sla_duration_minutes' => 720,  'warning_threshold_minutes' => 90,  'notification_email' => 'order@astonhill.ae'],
            ['module_key' => 'field_submissions',        'module_name' => 'Field Submissions (Assignment SLA)','sla_duration_minutes' => 240,  'warning_threshold_minutes' => 30,  'notification_email' => 'order@astonhill.ae'],
            ['module_key' => 'customer_support_requests','module_name' => 'Customer Support Requests',         'sla_duration_minutes' => 1440, 'warning_threshold_minutes' => 120, 'notification_email' => 'order@astonhill.ae'],
            ['module_key' => 'vas_requests',             'module_name' => 'VAS Requests',                      'sla_duration_minutes' => 960,  'warning_threshold_minutes' => 90,  'notification_email' => 'order@astonhill.ae'],
            ['module_key' => 'field_head_queue',         'module_name' => 'Field Head Queue',                  'sla_duration_minutes' => 480,  'warning_threshold_minutes' => 60,  'notification_email' => 'order@astonhill.ae'],
        ];

        foreach ($rules as $rule) {
            SlaRule::firstOrCreate(
                ['module_key' => $rule['module_key']],
                array_merge($rule, ['is_active' => true])
            );
        }
    }
}
