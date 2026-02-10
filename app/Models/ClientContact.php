<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientContact extends Model
{
    protected $table = 'client_contacts';

    protected $fillable = [
        'client_id',
        'sort_order',
        'name',
        'designation',
        'contact_number',
        'alternate_number',
        'email',
        'as_updated_or_not',
        'as_expiry_date',
        'additional_note',
    ];

    protected $casts = [
        'as_expiry_date' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
