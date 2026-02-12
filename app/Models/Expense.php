<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUSES = ['pending', 'approved'];

    protected $fillable = [
        'user_id',
        'expense_date',
        'product_category',
        'product_description',
        'invoice_number',
        'vat_amount',
        'amount_without_vat',
        'full_amount',
        'status',
        'comment',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'vat_amount' => 'float',
        'amount_without_vat' => 'decimal:2',
        'full_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ExpenseAudit::class);
    }

    public function getVatAmountAttribute(): float
    {
        $rate = (float)($this->vat_amount ?? 0);
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
            $q->where('invoice_number', 'like', '%' . addcslashes($filters['invoice'], '%_\\') . '%');
        }
        if (!empty($filters['product_description'])) {
            $q->where('product_description', 'like', '%' . addcslashes($filters['product_description'], '%_\\') . '%');
        }

        if (!empty($filters['from'])) {
            $q->whereDate('expense_date', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $q->whereDate('expense_date', '<=', $filters['to']);
        }

        if (!empty($filters['created_from'])) {
            $q->whereDate('created_at', '>=', $filters['created_from']);
        }
        if (!empty($filters['created_to'])) {
            $q->whereDate('created_at', '<=', $filters['created_to']);
        }
        if (!empty($filters['added_by'])) {
            $term = '%' . addcslashes($filters['added_by'], '%_\\') . '%';
            $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', $term));
        }
        if (isset($filters['amount_min']) && $filters['amount_min'] !== '' && $filters['amount_min'] !== null) {
            $q->where('full_amount', '>=', (float) $filters['amount_min']);
        }
        if (isset($filters['amount_max']) && $filters['amount_max'] !== '' && $filters['amount_max'] !== null) {
            $q->where('full_amount', '<=', (float) $filters['amount_max']);
        }
        if (!empty($filters['vat_applicable'])) {
            if ($filters['vat_applicable'] === 'yes') {
                $q->whereNotNull('vat_amount')->where('vat_amount', '>', 0);
            } elseif ($filters['vat_applicable'] === 'no') {
                $q->where(function ($q2) {
                    $q2->whereNull('vat_amount')->orWhere('vat_amount', 0);
                });
            }
        }
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        return $q;
    }
}
