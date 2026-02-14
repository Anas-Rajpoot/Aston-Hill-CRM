<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use Illuminate\Database\Seeder;

class AuditLogsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['2026-01-20 14:52:15', 'Ahmed Hassen', 'Super Admin',   'updated',        'Security Settings', 'SEC-001',          '192.168.1.1',  'Chrome 130 / Windows 11', 'success'],
            ['2026-01-20 14:19:22', 'Sarah Johnson', 'Back Office Manager', 'assigned', 'Lead Submissions',  'LD-2026-0815',    '192.168.1.78', 'Chrome 130 / macOS 15',   'success'],
            ['2026-01-19 13:50:01', 'Mohammed Ali',  'Back Office Executive', 'updated','Lead Submissions',  'LS-0805-8044',    '192.168.1.29', 'Edge 130 / Windows 11',   'success'],
            ['2026-01-20 13:41:55', 'Fatima Ahmed',  'Field Operations Manager', 'created','Field Submissions','FS-0805-8043',  '192.168.1.14', 'Chrome 130 / Windows 11', 'success'],
            ['2026-01-20 13:30:18', 'John Smith',    'Customer Support Agent', 'login',  'Authentication',    null,              '192.168.1.1',  'Chrome 130 / Windows 11', 'success'],
            ['2026-01-20 13:12:45', 'Ahmed Hassen',  'Super Admin',   'access_change',  'Security Settings', 'SEC-082',         '192.168.1.1',  'Chrome 130 / Windows 11', 'success'],
            ['2026-01-20 13:08:22', 'Sarah Johnson', 'Back Office Manager', 'exported_data','Lead Submissions','EXPRT-2016-0812','192.168.1.15','Chrome 130 / Windows 11','success'],
            ['2026-01-20 12:55:10', 'Unknown User',  'N/A',           'login',          'Authentication',    null,              'ATN-2023-4158','Chrome 130 / Windows 11', 'failure'],
            ['2026-01-20 12:30:05', 'Mohammed Ali',  'Back Office Executive', 'updated', 'Clients',           'CL-0806-9800',   '192.168.1.5',  'Edge 130 / Windows 11',   'success'],
            ['2026-01-20 12:15:23', 'Ahmed Hassen',  'Super Admin',   'sla_change',     'SLA Configuration', 'SLA-001',         '192.168.1.1',  'Chrome 130 / Windows 11', 'success'],
            ['2026-01-20 11:47:45', 'John Smith',    'Customer Support Agent', 'assigned','Customer Support', 'CS-2025-0067',   '192.168.1.67', 'Chrome 130 / Windows 11', 'success'],
            ['2026-01-20 11:30:27', 'Sarah Johnson', 'Back Office Manager', 'updated',   'Employees',        'EMP-1810-0854',   '192.168.1.78', 'Chrome 130 / macOS 15',   'success'],
            ['2026-01-20 11:15:05', 'Mohammed Ali',  'Back Office Executive', 'password_reset','Authentication',null,           '192.168.1.5',  'Edge 520 / Windows 11',   'success'],
            ['2026-01-20 10:10:20', 'Fatima Ahmed',  'Field Operations Manager', 'updated','Field Submissions','FS-0506-8021', '192.168.1.14', 'Safari 18.1 / macOS 15',  'success'],
            ['2026-01-20 10:05:45', 'Ahmed Hassen',  'Super Admin',   'created',        'Employees',         'EMP-2810-0855',   '192.168.1.1',  'Chrome 130 / Windows 11', 'success'],
            ['2026-01-20 09:50:18', 'John Smith',    'Customer Support Agent', 'updated','Customer Support',  'CS-2025-0065',   '192.168.1.67', 'Chrome 130 / Windows 11', 'success'],
            ['2026-01-19 15:30:12', 'Sarah Johnson', 'Back Office Manager', 'login',     'Authentication',    null,              '192.168.1.78', 'Chrome 130 / macOS 15',   'success'],
            ['2026-01-19 14:22:35', 'Unknown User',  'N/A',           'login',          'Authentication',    null,              '45.33.67.89',  'Chrome 130 / Windows 11', 'failure'],
            ['2026-01-19 13:15:00', 'Mohammed Ali',  'Back Office Executive', 'created', 'VAS Requests',      'VAS-2025-0815',   '192.168.1.5',  'Edge 130 / Windows 11',   'success'],
            ['2026-01-19 11:40:25', 'Ahmed Hassen',  'Super Admin',   'updated',        'Notifications',     'NOTIF-001',       '192.168.1.1',  'Chrome 130 / Windows 11', 'success'],
            ['2026-01-19 10:30:00', 'Fatima Ahmed',  'Field Operations Manager', 'exported_data','Field Submissions','EXPRT-2016-0811','192.168.1.14','Chrome 130 / Windows 11','success'],
            ['2026-01-18 16:45:10', 'John Smith',    'Customer Support Agent', 'deleted','Customer Support',  'CS-2025-0060',   '192.168.1.67', 'Chrome 130 / Windows 11', 'success'],
            ['2026-01-18 14:20:05', 'Sarah Johnson', 'Back Office Manager', 'assigned', 'Lead Submissions',  'LD-2026-0810',   '192.168.1.78', 'Chrome 130 / macOS 15',   'success'],
            ['2026-01-18 11:05:30', 'Mohammed Ali',  'Back Office Executive', 'login',   'Authentication',    null,              '192.168.1.5',  'Edge 130 / Windows 11',   'success'],
            ['2026-01-17 15:30:00', 'Ahmed Hassen',  'Super Admin',   'updated',        'System Preferences','SYSPREF-001',    '192.168.1.1',  'Chrome 130 / Windows 11', 'success'],
        ];

        foreach ($rows as $r) {
            AuditLog::create([
                'occurred_at' => $r[0],
                'user_name'   => $r[1],
                'user_role'   => $r[2],
                'action'      => $r[4] === 'Authentication' ? $r[3] : $r[3],
                'module'      => $r[4],
                'record_id'   => $r[5],
                'record_ref'  => $r[5],
                'ip'          => $r[6],
                'device'      => $r[7],
                'user_agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'result'      => $r[8],
                'session_id'  => 'sess_' . substr(md5($r[1] . $r[0]), 0, 12),
                'method'      => match ($r[3]) { 'created' => 'POST', 'updated', 'assigned', 'sla_change', 'access_change', 'password_reset' => 'PUT', 'deleted' => 'DELETE', default => 'GET' },
                'route'       => 'api.' . strtolower(str_replace(' ', '-', $r[4])),
                'latency_ms'  => rand(45, 350),
                'old_values'  => in_array($r[3], ['updated', 'sla_change', 'access_change']) ? ['status' => 'old_value'] : null,
                'new_values'  => in_array($r[3], ['updated', 'created', 'sla_change', 'access_change']) ? ['status' => 'new_value'] : null,
            ]);
        }
    }
}
