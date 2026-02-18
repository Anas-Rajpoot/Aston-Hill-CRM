<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    public const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    protected $table = 'clients';

    protected $fillable = [
        'company_name',
        'account_number',
        'submitted_at',
        'manager_id',
        'team_leader_id',
        'sales_agent_id',
        'account_manager_id',
        'status',
        'service_type',
        'product_type',
        'address',
        'product_name',
        'mrc',
        'quantity',
        'other',
        'migration_numbers',
        'fiber',
        'order_number',
        'wo_number',
        'completion_date',
        'payment_connection',
        'contract_type',
        'contract_end_date',
        'renewal_alert',
        'additional_notes',
        'created_by',
        'revenue',
        'csr_name_1',
        'csr_name_2',
        'csr_name_3',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'completion_date' => 'date',
        'contract_end_date' => 'date',
        'revenue' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function teamLeader()
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function salesAgent()
    {
        return $this->belongsTo(User::class, 'sales_agent_id');
    }

    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function csrs()
    {
        return $this->hasMany(ClientCsr::class)->orderBy('sort_order');
    }

    public function companyDetail()
    {
        return $this->hasOne(ClientCompanyDetail::class);
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class)->orderBy('sort_order');
    }

    public function addresses()
    {
        return $this->hasMany(ClientAddress::class)->orderBy('sort_order');
    }

    public function alerts()
    {
        return $this->hasMany(ClientAlert::class);
    }

    public function audits()
    {
        return $this->hasMany(ClientAudit::class)->orderByDesc('changed_at');
    }

    public function leadSubmissions()
    {
        return $this->hasMany(LeadSubmission::class);
    }

    public function fieldSubmissions()
    {
        return $this->hasMany(FieldSubmission::class);
    }

    public function vasRequests()
    {
        return $this->hasMany(VasRequestSubmission::class);
    }

    public function customerSupportSubmissions()
    {
        return $this->hasMany(CustomerSupportSubmission::class);
    }
}
