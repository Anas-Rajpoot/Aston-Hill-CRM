@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold">Expenses</h2>
            <p class="text-sm text-gray-500">Track expenses with filters and export.</p>
        </div>

        @can('expenses.create')
            <a href="{{ route('expenses.create') }}"
               class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm">Add Expense</a>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="bg-gray-50 border rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            @can('expenses.view_all')
            <div>
                <label class="text-xs font-medium text-gray-600">User</label>
                <select id="f_user" class="w-full border-gray-300 rounded-md">
                    <option value="">All Users</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>
            @endcan

            <div>
                <label class="text-xs font-medium text-gray-600">Category</label>
                <input id="f_category" class="w-full border-gray-300 rounded-md" placeholder="e.g. Tools, Fuel" />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Invoice</label>
                <input id="f_invoice" class="w-full border-gray-300 rounded-md" placeholder="Invoice contains..." />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Status</label>
                <select id="f_preset" class="w-full border-gray-300 rounded-md">
                    <option value="this_month">This month</option>
                    <option value="last_month">Last month</option>
                    <option value="quarter">This quarter</option>
                    <option value="last_6_months">Last 6 months</option>
                    <option value="this_year">This year</option>
                    <option value="last_year">Last year</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">From</label>
                <input id="f_from" type="date" class="w-full border-gray-300 rounded-md" />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">To</label>
                <input id="f_to" type="date" class="w-full border-gray-300 rounded-md" />
            </div>

            <div class="flex items-end gap-2">
                <button id="btn_apply" class="px-4 py-2 rounded bg-gray-800 text-white text-sm">Apply</button>
                <button id="btn_reset" class="px-4 py-2 rounded bg-gray-200 text-gray-900 text-sm">Reset</button>
            </div>

            @can('expenses.export')
            <div class="flex items-end">
                <a id="btn_export" href="#" class="px-4 py-2 rounded bg-emerald-600 text-white text-sm w-full text-center">
                    Export CSV
                </a>
            </div>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto">
        <table id="expensesTable" class="min-w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">Date</th>
                    <th class="p-2 text-left">Category</th>
                    <th class="p-2 text-left">Description</th>
                    <th class="p-2 text-left">Invoice</th>
                    <th class="p-2 text-left">VAT</th>
                    <th class="p-2 text-left">Without VAT</th>
                    <th class="p-2 text-left">Full</th>
                    @can('expenses.view_all')
                    <th class="p-2 text-left">User</th>
                    @endcan
                    <th class="p-2 text-left">Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const table = $('#expensesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('expenses.datatable') }}",
            data: function (d) {
                d.user_id = document.getElementById('f_user')?.value || '';
                d.category = document.getElementById('f_category').value || '';
                d.invoice = document.getElementById('f_invoice').value || '';
                d.preset = document.getElementById('f_preset').value || '';
                d.from = document.getElementById('f_from').value || '';
                d.to = document.getElementById('f_to').value || '';
            }
        },
        columns: [
            { data: 'expense_date', name: 'expense_date' },
            { data: 'product_category', name: 'product_category' },
            { data: 'product_description', name: 'product_description', defaultContent: '—' },
            { data: 'invoice_number', name: 'invoice_number', defaultContent: '—' },
            { data: 'vat_amount', name: 'vat_amount' },
            { data: 'amount_without_vat', name: 'amount_without_vat' },
            { data: 'full_amount', name: 'full_amount' },
            @can('expenses.view_all')
            { data: 'user', name: 'user.name', orderable: false, searchable: false },
            @endcan
            { data: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']]
    });

    document.getElementById('btn_apply').addEventListener('click', () => table.ajax.reload());

    document.getElementById('btn_reset').addEventListener('click', () => {
        document.getElementById('f_user')?.value && (document.getElementById('f_user').value = '');
        document.getElementById('f_category').value = '';
        document.getElementById('f_invoice').value = '';
        document.getElementById('f_preset').value = 'this_month';
        document.getElementById('f_from').value = '';
        document.getElementById('f_to').value = '';
        table.ajax.reload();
    });

    @can('expenses.export')
    function buildExportUrl() {
        const base = "{{ route('expenses.export.csv') }}";
        const params = new URLSearchParams();
        const userId = document.getElementById('f_user')?.value || '';
        if (userId) params.set('user_id', userId);
        const category = document.getElementById('f_category').value || '';
        if (category) params.set('category', category);
        const invoice = document.getElementById('f_invoice').value || '';
        if (invoice) params.set('invoice', invoice);

        const preset = document.getElementById('f_preset').value || '';
        const from = document.getElementById('f_from').value || '';
        const to = document.getElementById('f_to').value || '';

        if (from || to) {
            if (from) params.set('from', from);
            if (to) params.set('to', to);
        } else {
            if (preset) params.set('preset', preset);
        }

        return base + '?' + params.toString();
    }

    const exportBtn = document.getElementById('btn_export');
    exportBtn.addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = buildExportUrl();
    });
    @endcan
});
</script>
@endsection
