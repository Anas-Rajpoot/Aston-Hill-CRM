<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Verifier extends Model
{
    protected $fillable = [
        'verifier_name',
        'verifier_number',
        'remarks',
    ];
}
