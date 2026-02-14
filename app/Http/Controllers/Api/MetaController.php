<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemPreference;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MetaController extends Controller
{
    /**
     * GET /api/meta/timezones – IANA timezone list with UTC offsets.
     */
    public function timezones(): JsonResponse
    {
        $list = Cache::remember('meta_timezones', 86400, function () {
            $identifiers = DateTimeZone::listIdentifiers();
            $now = new \DateTime('now', new DateTimeZone('UTC'));
            $out = [];
            foreach ($identifiers as $tz) {
                $zone = new DateTimeZone($tz);
                $offset = $zone->getOffset($now);
                $hours = intdiv($offset, 3600);
                $mins = abs(intdiv($offset % 3600, 60));
                $label = sprintf('%s (UTC%+d%s)', $tz, $hours, $mins ? sprintf(':%02d', $mins) : '');
                $out[] = ['value' => $tz, 'label' => $label, 'offset' => $offset];
            }
            usort($out, fn ($a, $b) => $a['offset'] <=> $b['offset'] ?: strcmp($a['value'], $b['value']));

            return $out;
        });

        return response()->json($list);
    }

    /**
     * GET /api/meta/landing-pages – allowed landing page options.
     */
    public function landingPages(): JsonResponse
    {
        $labels = [
            'dashboard'        => 'Dashboard',
            'submissions'      => 'Submissions',
            'lead-submissions' => 'Lead Submissions',
            'field-submissions'=> 'Field Submissions',
            'customer-support' => 'Customer Support',
            'vas-requests'     => 'VAS Requests',
            'clients'          => 'Clients',
            'order-status'     => 'Order Status',
            'dsp-tracker'      => 'DSP Tracker',
            'verifiers-detail' => 'Verifiers Detail',
            'employees'        => 'Employees',
            'cisco-extensions' => 'Cisco Extensions',
            'attendance-log'   => 'Attendance Log',
            'expenses'         => 'Expense Tracker',
            'personal-notes'   => 'Personal Notes',
            'email-followups'  => 'Email Follow Up',
            'reports'          => 'Reports',
            'settings'         => 'Settings',
        ];

        $pages = array_map(
            fn ($v) => ['value' => $v, 'label' => $labels[$v] ?? ucfirst(str_replace('-', ' ', $v))],
            SystemPreference::LANDING_PAGES
        );

        return response()->json($pages);
    }
}
