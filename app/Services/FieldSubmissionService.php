<?php

namespace App\Services;

use App\Models\FieldSubmission;
use Illuminate\Support\Facades\DB;

class FieldSubmissionService
{
    public function create(array $data, int $userId): FieldSubmission
    {
        return DB::transaction(function () use ($data, $userId) {
            return FieldSubmission::create([
                ...$data,
                'created_by' => $userId,
                'status' => 'draft',
            ]);
        });
    }

    public function submit(FieldSubmission $fieldSubmission): FieldSubmission
    {
        $fieldSubmission->submit();
        return $fieldSubmission->fresh();
    }
}
