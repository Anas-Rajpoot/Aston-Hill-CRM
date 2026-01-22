<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'user_id',
        'expense_date',
        'product_category',
        'product_description',
        'invoice_number',
        'vat_rate',
        'amount_without_vat',
        'full_amount',
        'comment',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'vat_rate' => 'decimal:2',
        'amount_without_vat' => 'decimal:2',
        'full_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getVatAmountAttribute(): float
    {
        $rate = (float)($this->vat_rate ?? 0);
        $net  = (float)($this->amount_without_vat ?? 0);
        return round($net * ($rate / 100), 2);
    }

    public function scopeFilter(Builder $q, array $filters): Builder
    {
        if (!empty($filters['user_id'])) {
            $q->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['category'])) {
            $q->where('product_category', $filters['category']);
        }

        if (!empty($filters['invoice'])) {
            $q->where('invoice_number', 'like', '%' . $filters['invoice'] . '%');
        }

        if (!empty($filters['from'])) {
            $q->whereDate('expense_date', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $q->whereDate('expense_date', '<=', $filters['to']);
        }

        return $q;
    }
}
