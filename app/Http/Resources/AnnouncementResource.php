<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray($request): array
    {
        // Build audience chips for the table
        $chips = [];
        if ($this->all_users) {
            $chips[] = 'All Users';
        } else {
            $aud = $this->audiences ?? [];
            foreach (['roles', 'departments', 'locations', 'user_groups'] as $key) {
                foreach ($aud[$key] ?? [] as $v) {
                    $chips[] = $v;
                }
            }
        }

        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'type'            => $this->type,
            'body'            => $this->body,
            'link_url'        => $this->link_url,
            'link_label'      => $this->link_label,
            'priority'        => $this->priority,
            'all_users'       => (bool) $this->all_users,
            'audiences'       => $this->audiences,
            'audience_chips'  => $chips,
            'channels'        => $this->channels ?? ['web'],
            'is_pinned'       => (bool) $this->is_pinned,
            'require_ack'     => (bool) $this->require_ack,
            'ack_due_at'      => $this->ack_due_at?->toIso8601String(),
            'published_at'    => $this->published_at?->toIso8601String(),
            'expire_at'       => $this->expire_at?->toIso8601String(),
            'archived_at'     => $this->archived_at?->toIso8601String(),
            'status'          => $this->status,
            'created_by'      => $this->created_by,
            'creator_name'    => $this->creator?->name ?? '—',
            'updated_at'      => $this->updated_at?->toIso8601String(),
            'ack_count'       => $this->when($this->require_ack, fn () => $this->acknowledgements_count ?? $this->acknowledgements()->count()),
        ];
    }
}
