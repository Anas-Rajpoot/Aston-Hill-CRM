<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $safe = static function ($value) {
            if (! is_string($value)) {
                return $value;
            }

            return strip_tags($value);
        };

        $row = (array) $this->resource;

        foreach ($row as $key => $value) {
            $row[$key] = $safe($value);
        }

        return $row;
    }
}

