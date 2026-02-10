<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientCompanyDetail extends Model
{
    protected $table = 'client_company_details';

    protected $fillable = [
        'client_id',
        'trade_license_issuing_authority',
        'company_category',
        'trade_license_number',
        'trade_license_expiry_date',
        'establishment_card_number',
        'establishment_card_expiry_date',
        'account_taken_from',
        'account_mapping_date',
        'account_transfer_given_to',
        'account_transfer_given_date',
        'account_manager_name',
        'csr_name_1',
        'csr_name_2',
        'csr_name_3',
        'first_bill',
        'second_bill',
        'third_bill',
        'fourth_bill',
        'additional_comment_1',
        'additional_comment_2',
    ];

    protected $casts = [
        'trade_license_expiry_date' => 'date',
        'establishment_card_expiry_date' => 'date',
        'account_mapping_date' => 'date',
        'account_transfer_given_date' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
