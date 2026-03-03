<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VasRequestResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'account_number' => $this->account_number,
            'request_type' => $this->request_type,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'manager_id' => $this->manager_id,
            'team_leader_id' => $this->team_leader_id,
            'sales_agent_id' => $this->sales_agent_id,
            'back_office_executive_id' => $this->back_office_executive_id,
        ];

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
        if ($this->relationLoaded('backOfficeExecutive')) {
            $data['executive'] = $this->backOfficeExecutive ? ['id' => $this->backOfficeExecutive->id, 'name' => $this->backOfficeExecutive->name] : null;
        }

        foreach (['contact_number', 'request_description', 'additional_notes'] as $field) {
            if (isset($this->attributes[$field]) || array_key_exists($field, $this->attributes ?? [])) {
                $data[$field] = $this->{$field};
            }
        }

        if (! isset($data['request_description'])) {
            $data['request_description'] = $this->request_description ?? $this->description;
        }
        if (! isset($data['description'])) {
            $data['description'] = $this->description ?? $this->request_description;
        }

        return $data;
    }
}
