<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->hasRole('superadmin') || $user->can('teams.edit'));
    }

    public function rules(): array
    {
        $teamId = $this->route('team')?->id ?? $this->route('team');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($teamId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'department' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'max_members' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A team with this name already exists.',
            'max_members.min' => 'Maximum members must be at least 1.',
        ];
    }
}
