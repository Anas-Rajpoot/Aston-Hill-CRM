<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->hasRole('superadmin') || $user->can('teams.create'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
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
