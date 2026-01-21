<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\Models\UserLoginLog;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LoginLogsExport implements FromCollection
{
    public function __construct(public array $filters = []) {}
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        //
    }

    public function query()
    {
        $q = UserLoginLog::query()->with('user');

        if (!empty($this->filters['user_id'])) {
            $q->where('user_id', $this->filters['user_id']);
        }
        if (!empty($this->filters['role'])) {
            $q->where('role', $this->filters['role']);
        }
        if (!empty($this->filters['from']) && !empty($this->filters['to'])) {
            $q->whereBetween('login_at', [
                $this->filters['from'].' 00:00:00',
                $this->filters['to'].' 23:59:59',
            ]);
        }

        return $q->select('user_id','role','ip_address','country','country_code','login_at','logout_at','is_suspicious','suspicious_reason');
    }

    public function headings(): array
    {
        return ['user_id','role','ip','country','country_code','login_at','logout_at','suspicious','reason'];
    }
}
