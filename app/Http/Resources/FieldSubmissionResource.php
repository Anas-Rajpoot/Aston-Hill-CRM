<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FieldSubmissionResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'account_number' => $this->account_number,
            'company_name' => $this->company_name,
            'authorized_signatory_name' => $this->authorized_signatory_name,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'manager_id' => $this->manager_id,
            'team_leader_id' => $this->team_leader_id,
            'sales_agent_id' => $this->sales_agent_id,
            'field_executive_id' => $this->field_executive_id,
        ];

        // Only include loaded relationships (avoids N+1)
        if ($this->relationLoaded('creator')) {
            $data['creator'] = $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null;
        }
        if ($this->relationLoaded('salesAgent')) {
            $data['sales_agent'] = $this->salesAgent ? ['id' => $this->salesAgent->id, 'name' => $this->salesAgent->name] : null;
        }
        if ($this->relationLoaded('teamLeader')) {
            $data['team_leader'] = $this->teamLeader ? ['id' => $this->teamLeader->id, 'name' => $this->teamLeader->name] : null;
        }
        if ($this->relationLoaded('manager')) {
            $data['manager'] = $this->manager ? ['id' => $this->manager->id, 'name' => $this->manager->name] : null;
        }
        if ($this->relationLoaded('fieldExecutive')) {
            $data['field_executive'] = $this->fieldExecutive ? ['id' => $this->fieldExecutive->id, 'name' => $this->fieldExecutive->name] : null;
        }

        // Scalar fields that may or may not be selected
        foreach (['contact_number', 'product', 'alternate_number', 'emirates', 'location_coordinates', 'complete_address', 'additional_notes', 'special_instruction', 'meeting_date', 'field_status', 'remarks_by_field_agent'] as $field) {
            if (isset($this->attributes[$field]) || array_key_exists($field, $this->attributes ?? [])) {
                $data[$field] = $this->{$field};
            }
        }

        return $data;
    }
}
