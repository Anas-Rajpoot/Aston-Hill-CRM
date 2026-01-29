<?php

namespace App\Support;

use App\Models\ServiceType;

class LeadSubmissionSchema
{
    public static function fields(ServiceType $type): array
    {
        // Expect schema like: ['fields'=>[...], 'documents'=>[...]]
        return $type->schema['fields'] ?? [];
    }

    public static function documents(ServiceType $type): array
    {
        return $type->schema['documents'] ?? [];
    }
}
