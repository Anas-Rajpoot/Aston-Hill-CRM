<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.create');
    }

    public function rules(): array
    {
        return [
            'expense_date' => ['required','date'],
            'product_category' => ['required','string','max:190'],
            'product_description' => ['nullable','string'],
            'invoice_number' => ['nullable','string','max:190'],
            'vat_amount' => ['nullable','numeric','min:0','max:100'],
            'amount_without_vat' => ['required','numeric','min:0'],
            'full_amount' => ['nullable','numeric','min:0'], // can be auto-calculated
            'comment' => ['nullable','string'],
        ];
    }
}
