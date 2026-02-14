<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LibraryDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'document_code'   => $this->document_code,
            'name'            => $this->name,
            'description'     => $this->description,
            'category_id'     => $this->category_id,
            'category_name'   => $this->category?->name,
            'module_keys'     => $this->module_keys ?? [],
            'tags'            => $this->tags ?? [],
            'visibility'      => $this->visibility,
            'allowed_roles'   => $this->allowed_roles ?? [],
            'allowed_departments' => $this->allowed_departments ?? [],
            'file_type'       => $this->file_type,
            'mime_type'       => $this->mime_type,
            'size_bytes'      => $this->size_bytes,
            'size_human'      => $this->size_human,
            'current_version' => $this->current_version,
            'status'          => $this->status,
            'previewable'     => $this->is_previewable,
            'uploaded_by'     => $this->uploaded_by,
            'uploaded_by_name'=> $this->uploader?->name ?? '—',
            'uploaded_on'     => $this->created_at?->toIso8601String(),
            'updated_on'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
