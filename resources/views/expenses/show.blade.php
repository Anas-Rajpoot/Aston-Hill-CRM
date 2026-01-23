@extends('layouts.app')

@section('content')
@php use App\Support\Format; @endphp

<div class="bg-white rounded-xl shadow p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">Expense #{{ $expense->id }}</h2>
        <div class="flex gap-2">
            @can('expenses.export')
            <a class="px-3 py-2 rounded bg-emerald-600 text-white text-sm"
               href="{{ route('expenses.export.single', $expense) }}">Export CSV</a>
            @endcan
            <a href="{{ route('expenses.index') }}" class="text-sm text-gray-600 hover:underline pt-2">Back</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div class="border rounded-lg p-4">
            <p class="text-gray-500">Date</p>
            <p class="font-semibold">{{ Format::d($expense->expense_date) }}</p>
        </div>

        <div class="border rounded-lg p-4">
            <p class="text-gray-500">Category</p>
            <p class="font-semibold">{{ $expense->product_category }}</p>
        </div>

        <div class="border rounded-lg p-4 md:col-span-2">
            <p class="text-gray-500">Description</p>
            <p class="font-semibold">{{ $expense->product_description ?? '—' }}</p>
        </div>

        <div class="border rounded-lg p-4">
            <p class="text-gray-500">Invoice Number</p>
            <p class="font-semibold">{{ $expense->invoice_number ?? '—' }}</p>
        </div>

        <div class="border rounded-lg p-4">
            <p class="text-gray-500">VAT</p>
            <p class="font-semibold">{{ Format::money($expense->vat_amount) }}</p>
        </div>

        <div class="border rounded-lg p-4">
            <p class="text-gray-500">Amount without VAT</p>
            <p class="font-semibold">{{ Format::money($expense->amount_without_vat) }}</p>
        </div>

        <div class="border rounded-lg p-4">
            <p class="text-gray-500">Full Amount</p>
            <p class="font-semibold">{{ Format::money($expense->full_amount) }}</p>
        </div>

        <div class="border rounded-lg p-4 md:col-span-2">
            <p class="text-gray-500">Comment</p>
            <p class="font-semibold">{{ $expense->comment ?? '—' }}</p>
        </div>

        @can('expenses.view_all')
        <div class="border rounded-lg p-4 md:col-span-2">
            <p class="text-gray-500">Created By</p>
            <p class="font-semibold">{{ optional($expense->user)->name }} ({{ optional($expense->user)->email }})</p>
        </div>
        @endcan
    </div>
</div>
@endsection
