<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveColumnPreferenceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'columns' => ['required', 'array']
        ];
    }
}
