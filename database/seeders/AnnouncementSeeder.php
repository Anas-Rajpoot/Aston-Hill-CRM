<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::limit(2)->get();
        $u1 = $users[0]->id ?? 1;
        $u2 = $users[1]->id ?? $u1;

        $now = Carbon::now();

        $rows = [
            ['title' => 'System Maintenance Scheduled - January 2025', 'type' => 'text', 'priority' => 'high',    'all_users' => true, 'audiences' => null, 'channels' => ['web'], 'published_at' => $now->copy()->subDays(40), 'expire_at' => $now->copy()->subDays(25), 'created_by' => $u1, 'body' => 'System maintenance is scheduled for January. Please save your work before the scheduled window.'],
            ['title' => 'New SLA Policy Effective Immediately',        'type' => 'text', 'priority' => 'critical','all_users' => false,'audiences' => ['roles' => ['Customer Support', 'Back Office']], 'channels' => ['web', 'email'], 'published_at' => $now->copy()->subDays(42), 'expire_at' => null, 'created_by' => $u2, 'body' => 'A new SLA policy is now in effect. Please review the updated SLA timers in Settings > SLA Configuration.'],
            ['title' => 'Welcome New Team Members',                    'type' => 'text', 'priority' => 'normal',  'all_users' => true, 'audiences' => null, 'channels' => ['web'], 'published_at' => $now->copy()->subDays(43), 'expire_at' => $now->copy()->subDays(30), 'created_by' => $u1, 'body' => 'We are delighted to welcome our new team members joining this month. Please make them feel at home!'],
            ['title' => 'Q4 Performance Review Reminder',              'type' => 'text', 'priority' => 'normal',  'all_users' => false,'audiences' => ['roles' => ['Sales', 'Back Office']], 'channels' => ['web', 'email'], 'published_at' => $now->copy()->subDays(48), 'expire_at' => $now->copy()->subDays(34), 'created_by' => $u2, 'body' => 'Please complete your Q4 self-assessment by the deadline.'],
            ['title' => 'Holiday Schedule - January 2025',             'type' => 'text', 'priority' => 'low',     'all_users' => true, 'audiences' => null, 'channels' => ['web'], 'published_at' => $now->copy()->subDays(56), 'expire_at' => $now->copy()->subDays(43), 'created_by' => $u1, 'body' => 'Please note the updated holiday schedule for January.'],
            ['title' => 'Security Update - Two-Factor Authentication', 'type' => 'text', 'priority' => 'high',    'all_users' => true, 'audiences' => null, 'channels' => ['web', 'email'], 'published_at' => $now->copy()->subDays(40), 'expire_at' => $now->copy()->addDays(30), 'created_by' => $u2, 'body' => 'Two-factor authentication is now mandatory for all users. Please configure it in your profile.'],
            ['title' => 'Client Portal Launch Update',                 'type' => 'link', 'priority' => 'normal',  'all_users' => false,'audiences' => ['roles' => ['Sales', 'Customer Support']], 'channels' => ['web'], 'published_at' => $now->copy()->subDays(44), 'expire_at' => $now->copy()->subDays(20), 'link_url' => 'https://portal.example.com', 'link_label' => 'Visit Portal', 'created_by' => $u1, 'body' => null],
            ['title' => 'Monthly Team Meeting - January',              'type' => 'text', 'priority' => 'normal',  'all_users' => true, 'audiences' => null, 'channels' => ['web'], 'published_at' => $now->copy()->subDays(46), 'expire_at' => $now->copy()->subDays(37), 'created_by' => $u2, 'body' => 'The monthly team meeting is scheduled. Agenda will be shared shortly.'],
            ['title' => 'Updated Expense Reimbursement Policy',        'type' => 'text', 'priority' => 'normal',  'all_users' => false,'audiences' => ['roles' => ['Sales', 'Field Agent']], 'channels' => ['web'], 'published_at' => $now->copy()->subDays(47), 'expire_at' => null, 'created_by' => $u1, 'body' => 'The expense reimbursement policy has been updated. Please review.'],
            ['title' => 'Database Performance Improvements Completed', 'type' => 'text', 'priority' => 'low',     'all_users' => true, 'audiences' => null, 'channels' => ['web'], 'published_at' => $now->copy()->subDays(39), 'expire_at' => $now->copy()->subDays(26), 'created_by' => $u2, 'body' => 'Database performance improvements have been deployed successfully.'],
            // Scheduled (future)
            ['title' => 'Upcoming System Upgrade - February 2026',     'type' => 'banner','priority' => 'critical','all_users' => true, 'audiences' => null, 'channels' => ['web', 'email'], 'published_at' => $now->copy()->addDays(3), 'expire_at' => $now->copy()->addDays(17), 'created_by' => $u1, 'body' => 'A major system upgrade is coming next week.', 'link_url' => null, 'link_label' => null],
            // Active
            ['title' => 'CRM Mobile App Beta Now Available',           'type' => 'link', 'priority' => 'normal',  'all_users' => true, 'audiences' => null, 'channels' => ['web'], 'published_at' => $now->copy()->subDays(5), 'expire_at' => $now->copy()->addDays(25), 'link_url' => 'https://beta.example.com', 'link_label' => 'Try Beta', 'created_by' => $u2, 'body' => null],
            ['title' => 'Mandatory Compliance Training Deadline',      'type' => 'text', 'priority' => 'high',    'all_users' => true, 'audiences' => null, 'channels' => ['web', 'email'], 'published_at' => $now->copy()->subDays(10), 'expire_at' => $now->copy()->addDays(5), 'require_ack' => true, 'ack_due_at' => $now->copy()->addDays(5), 'created_by' => $u1, 'body' => 'All employees must complete the compliance training by the deadline.'],
        ];

        foreach ($rows as $r) {
            Announcement::firstOrCreate(
                ['title' => $r['title']],
                array_merge($r, ['is_pinned' => false, 'require_ack' => $r['require_ack'] ?? false])
            );
        }
    }
}
