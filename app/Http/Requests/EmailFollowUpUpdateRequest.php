<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmailFollowUpUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email_date'    => ['required','date'],
            'subject'       => ['required','string','max:255'],
            'category'      => ['required','string','max:100'],
            'request_from'  => ['nullable','string','max:190'],
            'sent_to'       => ['nullable','string','max:190'],
            'comment'       => ['nullable','string'],
        ];
    }
}
