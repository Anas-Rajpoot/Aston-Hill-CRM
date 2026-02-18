<?php

namespace App\Traits;

use App\Models\Client;

/**
 * Automatically sets `client_id` on submission models by matching
 * the submission's `account_number` to a Client record.
 *
 * Usage: `use ResolvesClientLink;` in any model that has both
 * `account_number` and `client_id` columns.
 */
trait ResolvesClientLink
{
    public static function bootResolvesClientLink(): void
    {
        static::saving(function ($model) {
            $model->resolveClientId();
        });
    }

    public function resolveClientId(): void
    {
        $accountNumber = trim((string) ($this->account_number ?? ''));

        if ($accountNumber === '') {
            $this->client_id = null;
            return;
        }

        $client = Client::whereRaw('LOWER(TRIM(account_number)) = ?', [strtolower($accountNumber)])->first();

        $this->client_id = $client?->id;
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
