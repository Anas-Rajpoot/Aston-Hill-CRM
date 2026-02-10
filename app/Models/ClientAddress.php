<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAddress extends Model
{
    protected $table = 'client_addresses';

    protected $fillable = [
        'client_id',
        'sort_order',
        'full_address',
        'unit',
        'building',
        'area',
        'emirates',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
