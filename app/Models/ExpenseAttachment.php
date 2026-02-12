<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAttachment extends Model
{
    protected $fillable = [
        'expense_id',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'type',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function isImage(): bool
    {
        $mime = $this->mime_type ?? '';
        return str_starts_with($mime, 'image/');
    }
}
