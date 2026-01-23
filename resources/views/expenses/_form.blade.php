@php
  $isEdit = isset($expense);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="text-sm font-medium">Date</label>
        <input type="date" name="expense_date"
               value="{{ old('expense_date', $isEdit ? $expense->expense_date?->format('Y-m-d') : '') }}"
               class="w-full border-gray-300 rounded-md" required>
        @error('expense_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">Product Category</label>
        <input name="product_category"
               value="{{ old('product_category', $isEdit ? $expense->product_category : '') }}"
               class="w-full border-gray-300 rounded-md" required>
        @error('product_category') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="text-sm font-medium">Product Description</label>
        <textarea name="product_description" rows="3"
                  class="w-full border-gray-300 rounded-md">{{ old('product_description', $isEdit ? $expense->product_description : '') }}</textarea>
        @error('product_description') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">Invoice Number</label>
        <input name="invoice_number"
               value="{{ old('invoice_number', $isEdit ? $expense->invoice_number : '') }}"
               class="w-full border-gray-300 rounded-md">
        @error('invoice_number') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">VAT (amount)</label>
        <input type="number" step="0.01" min="0" name="vat_amount"
               value="{{ old('vat_amount', $isEdit ? $expense->vat_amount : 0) }}"
               class="w-full border-gray-300 rounded-md" required>
        @error('vat_amount') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">Amount without VAT</label>
        <input type="number" step="0.01" min="0" name="amount_without_vat"
               value="{{ old('amount_without_vat', $isEdit ? $expense->amount_without_vat : 0) }}"
               class="w-full border-gray-300 rounded-md" required>
        @error('amount_without_vat') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">Full Amount</label>
        <input type="number" step="0.01" min="0" name="full_amount"
               value="{{ old('full_amount', $isEdit ? $expense->full_amount : 0) }}"
               class="w-full border-gray-300 rounded-md" required>
        @error('full_amount') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="text-sm font-medium">Comment</label>
        <textarea name="comment" rows="3"
                  class="w-full border-gray-300 rounded-md">{{ old('comment', $isEdit ? $expense->comment : '') }}</textarea>
        @error('comment') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
</div>
