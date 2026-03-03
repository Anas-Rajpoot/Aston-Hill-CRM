<?php

namespace App\Services;

use App\Models\FieldSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FieldSubmissionService
{
    public function create(array $data, int $userId): FieldSubmission
    {
        return DB::transaction(function () use ($data, $userId) {
            $alternateNumber = $data['alternate_number']
                ?? $data['alternate_contact_number']
                ?? '';

            $payload = [
                ...$data,
                'alternate_number' => $alternateNumber,
                'created_by' => $userId,
                'status' => 'draft',
            ];

            $table = (new FieldSubmission())->getTable();
            $columns = array_flip(Schema::getColumnListing($table));
            $safePayload = array_intersect_key($payload, $columns);

            return FieldSubmission::create($safePayload);
        });
    }

    public function submit(FieldSubmission $fieldSubmission): FieldSubmission
    {
        $fieldSubmission->submit();
        return $fieldSubmission->fresh();
    }
}
