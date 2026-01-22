@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Expenses</h1>
        <p class="text-sm text-gray-500">Track expenses with filters and exports.</p>
    </div>

    <div class="flex gap-2">
        @can('expenses.export')
            <a href="#" id="exportCsvBtn"
               class="px-4 py-2 rounded-md bg-emerald-600 text-white text-sm hover:bg-emerald-700">
                Export CSV
            </a>
        @endcan

        @can('expenses.create')
            <a href="{{ route('expenses.create') }}"
               class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                Add Expense
            </a>
        @endcan
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
        @if(auth()->user()->hasRole('superadmin'))
        <div>
            <label class="text-xs text-gray-500">User</label>
            <select id="f_user_id" class="w-full border-gray-300 rounded-md">
                <option value="">All</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>
        </div>
        @endif

        <div>
            <label class="text-xs text-gray-500">Category</label>
            <select id="f_category" class="w-full border-gray-300 rounded-md">
                <option value="">All</option>
                @foreach($categories as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-xs text-gray-500">Invoice</label>
            <input id="f_invoice" type="text" class="w-full border-gray-300 rounded-md" placeholder="INV-...">
        </div>

        <div>
            <label class="text-xs text-gray-500">From</label>
            <input id="f_from" type="date" class="w-full border-gray-300 rounded-md">
        </div>

        <div>
            <label class="text-xs text-gray-500">To</label>
            <input id="f_to" type="date" class="w-full border-gray-300 rounded-md">
        </div>

        <div class="flex items-end gap-2">
            <button id="applyFilters"
                class="px-4 py-2 rounded-md bg-gray-900 text-white text-sm hover:bg-black">
                Apply
            </button>
            <button id="resetFilters"
                class="px-4 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200 border">
                Reset
            </button>
        </div>
    </div>

    @can('expenses.export')
    <div class="mt-4 border-t pt-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <div>
                <label class="text-xs text-gray-500">Export Range</label>
                <select id="e_range" class="w-full border-gray-300 rounded-md">
                    <option value="custom">Custom (from/to)</option>
                    <option value="month">Monthly</option>
                    <option value="quarter">Quarterly</option>
                    <option value="half_year">Last 6 months</option>
                    <option value="year">Yearly</option>
                </select>
            </div>

            <div>
                <label class="text-xs text-gray-500">Month (YYYY-MM)</label>
                <input id="e_month" type="month" class="w-full border-gray-300 rounded-md">
            </div>

            <div>
                <label class="text-xs text-gray-500">Year</label>
                <input id="e_year" type="number" class="w-full border-gray-300 rounded-md" placeholder="2026">
            </div>

            <div>
                <label class="text-xs text-gray-500">Quarter (1-4)</label>
                <input id="e_quarter" type="number" min="1" max="4" class="w-full border-gray-300 rounded-md" placeholder="1">
            </div>

            <div class="flex items-end">
                <p class="text-xs text-gray-500">
                    Export uses your filters + selected range.
                </p>
            </div>
        </div>
    </div>
    @endcan
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table id="expensesTable" class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-700">
                <tr>
                    <th class="p-3 text-left">Date</th>
                    <th class="p-3 text-left">User</th>
                    <th class="p-3 text-left">Category</th>
                    <th class="p-3 text-left">Invoice</th>
                    <th class="p-3 text-right">VAT</th>
                    <th class="p-3 text-right">Net</th>
                    <th class="p-3 text-right">Total</th>
                    <th class="p-3 text-left">Comment</th>
                    <th class="p-3 text-left">Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

{{-- You must include DataTables assets in your app.js/layout or via CDN. --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = $('#expensesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('expenses.datatable') }}",
            data: function (d) {
                d.user_id = $('#f_user_id').val();
                d.category = $('#f_category').val();
                d.invoice = $('#f_invoice').val();
                d.from = $('#f_from').val();
                d.to = $('#f_to').val();
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'expense_date', name: 'expense_date' },
            { data: 'user', name: 'user.name', orderable: false },
            { data: 'product_category', name: 'product_category' },
            { data: 'invoice_number', name: 'invoice_number' },
            { data: 'vat', name: 'vat_rate', className: 'text-right', orderable: true },
            { data: 'amount_without_vat', name: 'amount_without_vat', className: 'text-right' },
            { data: 'full_amount', name: 'full_amount', className: 'text-right' },
            { data: 'comment', name: 'comment', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
    });

    document.getElementById('applyFilters').addEventListener('click', () => table.ajax.reload());
    document.getElementById('resetFilters').addEventListener('click', () => {
        $('#f_user_id').val('');
        $('#f_category').val('');
        $('#f_invoice').val('');
        $('#f_from').val('');
        $('#f_to').val('');
        table.ajax.reload();
    });

    const exportBtn = document.getElementById('exportCsvBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();

            const params = new URLSearchParams();
