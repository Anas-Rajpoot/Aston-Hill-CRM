<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadSubmissionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'lead_submission_id' => $this->lead_submission_id,
            'company_name' => $this->company_name,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'creator' => $this->creator,
            'category' => $this->category,
            'type' => $this->type,
        ];
    }
}
