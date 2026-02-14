<?php

namespace App\Http\Resources;

use App\Models\SlaRule;
use Illuminate\Http\Resources\Json\JsonResource;

class SlaRuleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                         => $this->id,
            'module_key'                 => $this->module_key,
            'module_name'                => $this->module_name,
            'sla_duration_minutes'       => $this->sla_duration_minutes,
            'sla_duration_human'         => SlaRule::minutesToHuman($this->sla_duration_minutes),
            'warning_threshold_minutes'  => $this->warning_threshold_minutes,
            'warning_threshold_human'    => SlaRule::minutesToHuman($this->warning_threshold_minutes) . "\nbefore breach",
            'notification_email'         => $this->notification_email,
            'is_active'                  => (bool) $this->is_active,
            'updated_at'                 => $this->updated_at?->toIso8601String(),
        ];
    }
}
