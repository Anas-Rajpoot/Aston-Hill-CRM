<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && ($u->hasRole('superadmin') || $u->can('manage-announcements'));
    }

    public function rules(): array
    {
        return [
            'title'        => 'required|string|max:200',
            'type'         => ['required', Rule::in(Announcement::TYPES)],
            'body'         => 'nullable|string',
            'link_url'     => 'nullable|url|max:2048',
            'link_label'   => 'nullable|string|max:80',
            'priority'     => ['required', Rule::in(Announcement::PRIORITIES)],
            'all_users'    => 'boolean',
            'audiences'    => 'nullable|array',
            'audiences.roles'        => 'nullable|array',
            'audiences.roles.*'      => 'string|max:100',
            'audiences.departments'  => 'nullable|array',
            'audiences.departments.*'=> 'string|max:100',
            'channels'     => 'nullable|array',
            'channels.*'   => Rule::in(['web', 'email']),
            'is_pinned'    => 'boolean',
            'require_ack'  => 'boolean',
            'ack_due_at'   => 'nullable|date|after_or_equal:published_at',
            'published_at' => 'required|date',
            'expire_at'    => 'nullable|date|after:published_at',
        ];
    }

    public function messages(): array
    {
        return [
            'expire_at.after' => 'Expiry date must be after publish date.',
            'ack_due_at.after_or_equal' => 'Acknowledgement due date must be after publish date.',
        ];
    }
}
