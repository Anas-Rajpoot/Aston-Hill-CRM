<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'user_name'   => $this->user_name,
            'user_role'   => $this->user_role,
            'action'      => $this->action,
            'action_color'=> AuditLog::ACTION_COLORS[$this->action] ?? 'gray',
            'module'      => $this->module,
            'record_id'   => $this->record_id,
            'record_ref'  => $this->record_ref,
            'result'      => $this->result,
            'ip'          => $this->ip,
            'device'      => $this->device,
            'session_id'  => $this->session_id,
            'route'       => $this->route,
            'method'      => $this->method,
            'latency_ms'  => $this->latency_ms,
            'old_values'  => $this->old_values,
            'new_values'  => $this->new_values,
            'meta'        => $this->meta,
        ];
    }
}
