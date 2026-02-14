<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\NotificationEscalation;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\NotificationTrigger;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class NotificationConfigSeeder extends Seeder
{
    public function run(): void
    {
        /* ── Settings singleton ──────────────────────────────────── */
        NotificationSetting::firstOrCreate(['id' => 1], [
            'default_sender_email' => 'order@astonhill.ae',
            'cc_emails'            => ['operations@astonhill.ae', 'management@astonhill.ae'],
            'bcc_emails'           => [],
            'enable_email'         => true,
            'enable_web'           => true,
            'enable_sms'           => false,
            'enable_sla_alerts'    => true,
        ]);

        /* ── Triggers ────────────────────────────────────────────── */
        $triggers = [
            ['key' => 'new_submission_created',  'name' => 'New Submission Created',  'email_alert_enabled' => false],
            ['key' => 'submission_assigned',     'name' => 'Submission Assigned',     'email_alert_enabled' => false],
            ['key' => 'status_updated',          'name' => 'Status Updated',          'email_alert_enabled' => false],
            ['key' => 'sla_approaching_breach',  'name' => 'SLA Approaching Breach',  'email_alert_enabled' => true],
            ['key' => 'sla_breached',            'name' => 'SLA Breached',            'email_alert_enabled' => true],
            ['key' => 'sla_followed',            'name' => 'SLA Followed',            'email_alert_enabled' => false],
            ['key' => 'submission_rejected',     'name' => 'Submission Rejected',     'email_alert_enabled' => false],
            ['key' => 'submission_completed',    'name' => 'Submission Completed',    'email_alert_enabled' => false],
        ];

        foreach ($triggers as $t) {
            NotificationTrigger::firstOrCreate(['key' => $t['key']], [
                'name'                => $t['name'],
                'website_enabled'     => true,
                'email_enabled'       => true,
                'in_app_enabled'      => true,
                'email_alert_enabled' => $t['email_alert_enabled'],
                'is_active'           => true,
            ]);
        }

        /* ── Escalation levels ───────────────────────────────────── */
        NotificationEscalation::firstOrCreate(['level' => 1], [
            'to_emails' => ['manager@astonhill.ae'],
            'enabled'   => true,
        ]);

        /* ── Email templates ─────────────────────────────────────── */
        $vars = ['CompanyName', 'SubmissionRef', 'CreatedAt', 'AssignedTo', 'Status'];

        $templates = [
            [
                'trigger_key' => 'new_submission_created',
                'name'        => 'New Submission Notification',
                'subject'     => 'New Submission - {{CompanyName}}',
                'body'        => "Dear Team,\n\nThis is to notify you that a New Submission from {{CompanyName}} ({{SubmissionRef}}) has been received.\nCreated At: {{CreatedAt}}. Assigned To: {{AssignedTo}}.\n\nPlease review the details and take necessary action.\n\nAll the best,\nHR CRM System",
            ],
            [
                'trigger_key' => 'submission_assigned',
                'name'        => 'Assignment Notification',
                'subject'     => 'Task Assigned to You - {{SubmissionRef}}',
                'body'        => "Dear {{AssignedTo}},\n\nYou have been assigned submission {{SubmissionRef}} for {{CompanyName}}.\nPlease review and process at your earliest convenience.\n\nRegards,\nHR CRM System",
            ],
            [
                'trigger_key' => 'status_updated',
                'name'        => 'Status Update Notification',
                'subject'     => 'Status Update - {{CompanyName}}',
                'body'        => "Dear Team,\n\nThe status of submission {{SubmissionRef}} ({{CompanyName}}) has been updated to: {{Status}}.\n\nRegards,\nHR CRM System",
            ],
            [
                'trigger_key' => 'sla_approaching_breach',
                'name'        => 'SLA Warning Alert',
                'subject'     => 'SLA Warning - {{CompanyName}}',
                'body'        => "⚠️ SLA WARNING\n\nSubmission {{SubmissionRef}} ({{CompanyName}}) is approaching its SLA breach deadline.\nCurrent Status: {{Status}}\nAssigned To: {{AssignedTo}}\n\nPlease take immediate action.\n\nHR CRM System",
            ],
            [
                'trigger_key' => 'sla_breached',
                'name'        => 'SLA Breach Alert',
                'subject'     => 'SLA BREACHED - {{CompanyName}}',
                'body'        => "🚨 SLA BREACHED\n\nSubmission {{SubmissionRef}} ({{CompanyName}}) has breached its SLA deadline.\nCurrent Status: {{Status}}\nAssigned To: {{AssignedTo}}\n\nEscalation procedures have been initiated.\n\nHR CRM System",
            ],
        ];

        foreach ($templates as $t) {
            EmailTemplate::firstOrCreate(
                ['trigger_key' => $t['trigger_key']],
                array_merge($t, ['available_variables' => $vars])
            );
        }

        /* ── Sample notification logs ────────────────────────────── */
        $now = Carbon::now();
        $logSamples = [
            ['trigger_key' => 'sla_breached',           'channel' => 'email', 'module' => 'Lead Submissions',     'sent_to' => 'order@astonhill.ae',     'status' => 'sent',   'created_at' => $now->copy()->subHours(2)],
            ['trigger_key' => 'new_submission_created',  'channel' => 'email', 'module' => 'Field Submissions',    'sent_to' => 'ops@astonhill.ae',       'status' => 'sent',   'created_at' => $now->copy()->subHours(3)],
            ['trigger_key' => 'status_updated',          'channel' => 'web',   'module' => 'Customer Support',     'sent_to' => 'support@astonhill.ae',   'status' => 'sent',   'created_at' => $now->copy()->subHours(5)],
            ['trigger_key' => 'sla_approaching_breach',  'channel' => 'email', 'module' => 'Back Office Queue',    'sent_to' => 'order@astonhill.ae',     'status' => 'sent',   'created_at' => $now->copy()->subHours(8)],
            ['trigger_key' => 'sla_breached',            'channel' => 'email', 'module' => 'Lead Submissions',     'sent_to' => 'order@astonhill.ae',     'status' => 'sent',   'created_at' => $now->copy()->subHours(10)],
            ['trigger_key' => 'submission_assigned',     'channel' => 'web',   'module' => 'Lead Submissions',     'sent_to' => 'sales@astonhill.ae',     'status' => 'sent',   'created_at' => $now->copy()->subHours(12)],
            ['trigger_key' => 'sla_breached',            'channel' => 'email', 'module' => 'VAS Requests',         'sent_to' => 'order@astonhill.ae',     'status' => 'sent',   'created_at' => $now->copy()->subDay()],
            ['trigger_key' => 'new_submission_created',  'channel' => 'email', 'module' => 'Field Submissions',    'sent_to' => 'ops@astonhill.ae',       'status' => 'sent',   'created_at' => $now->copy()->subDay()->subHours(2)],
            ['trigger_key' => 'status_updated',          'channel' => 'email', 'module' => 'Field Submissions',    'sent_to' => 'hr@astonhill.ae',        'status' => 'failed', 'error' => 'SMTP connection timed out', 'created_at' => $now->copy()->subDays(2)],
        ];

        foreach ($logSamples as $log) {
            NotificationLog::create($log);
        }
    }
}
