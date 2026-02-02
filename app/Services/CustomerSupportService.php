<?php

namespace App\Services;

use App\Models\CustomerSupportSubmission;
use Illuminate\Support\Facades\DB;

class CustomerSupportService
{
    public function create(array $data, int $userId): CustomerSupportSubmission
    {
        return DB::transaction(function () use ($data, $userId) {
            return CustomerSupportSubmission::create([
                ...$data,
                'created_by' => $userId,
                'status' => 'draft',
            ]);
        });
    }

    public function submit(CustomerSupportSubmission $submission): CustomerSupportSubmission
    {
        $submission->submit();
        return $submission->fresh();
    }
}
