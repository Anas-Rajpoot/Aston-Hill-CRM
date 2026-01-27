<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceType extends Model
{
    protected $fillable = [
        'service_category_id',
        'name',
        'slug',
        'description',
        'schema',        // JSON definition: fields + documents
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'schema' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }
}
