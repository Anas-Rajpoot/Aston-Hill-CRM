<?php

namespace App\Http\Requests;

use App\Support\RbacPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && RbacPermission::can($user, 'teams', 'update', ['teams.edit']);
    }

    public function rules(): array
    {
        $teamId = $this->route('team')?->id ?? $this->route('team');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($teamId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'manager_ids' => ['nullable', 'array'],
            'manager_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'team_leader_ids' => ['nullable', 'array'],
            'team_leader_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'department' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'max_members' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $managerIds = collect($this->input('manager_ids', []))->map(fn ($id) => (int) $id)->filter();
            $leaderIds = collect($this->input('team_leader_ids', []))->map(fn ($id) => (int) $id)->filter();
            $memberIds = collect($this->input('member_ids', []))->map(fn ($id) => (int) $id)->filter();

            if ($managerIds->intersect($leaderIds)->isNotEmpty()) {
                $validator->errors()->add('team_leader_ids', 'A selected manager cannot also be selected as team leader.');
            }
            if ($managerIds->intersect($memberIds)->isNotEmpty()) {
                $validator->errors()->add('member_ids', 'A selected manager cannot also be selected as team member.');
            }
            if ($leaderIds->intersect($memberIds)->isNotEmpty()) {
                $validator->errors()->add('member_ids', 'A selected team leader cannot also be selected as team member.');
            }

            $maxMembers = (int) $this->input('max_members', 0);
            if ($maxMembers > 0) {
                $totalAssigned = $managerIds->count() + $leaderIds->count() + $memberIds->count();
                if ($totalAssigned > $maxMembers) {
                    $validator->errors()->add('max_members', "Total assigned users ({$totalAssigned}) exceeds the max members limit ({$maxMembers}).");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A team with this name already exists.',
            'max_members.min' => 'Maximum members must be at least 1.',
        ];
    }
}
