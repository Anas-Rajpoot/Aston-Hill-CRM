@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold">Login Logs</h1>
        <p class="text-sm text-gray-500">Track login/logout sessions, filter by role/user/date, export CSV, force logout.</p>
    </div>

    <a id="exportBtn"
       class="px-4 py-2 rounded bg-emerald-600 text-white text-sm"
       href="{{ route('login-logs.export.csv') }}">
        Export CSV
    </a>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-4">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div>
            <label class="text-xs font-medium text-gray-600">User</label>
            <select id="filter_user" class="w-full border rounded px-3 py-2">
                <option value="">All users</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600">Role</label>
            <select id="filter_role" class="w-full border rounded px-3 py-2">
                <option value="">All roles</option>
                @foreach($roles as $r)
                    <option value="{{ $r->name }}">{{ $r->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600">From</label>
            <input id="filter_from" type="date" class="w-full border rounded px-3 py-2" />
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600">To</label>
            <input id="filter_to" type="date" class="w-full border rounded px-3 py-2" />
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600">Status</label>
            <select id="filter_status" class="w-full border rounded px-3 py-2">
                <option value="">All</option>
                <option value="online">Online</option>
                <option value="offline">Offline</option>
            </select>
        </div>
    </div>

    <div class="mt-3 flex gap-2">
        <button id="applyFilters" class="px-4 py-2 rounded bg-indigo-600 text-white text-sm">Apply</button>
        <button id="resetFilters" class="px-4 py-2 rounded bg-gray-200 text-gray-800 text-sm">Reset</button>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-4">
    <table id="logsTable" class="w-full text-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Role</th>
                <th>Login</th>
                <th>Logout</th>
                <th>Status</th>
                <th>Duration</th>
                <th>IP</th>
                <th>Suspicious</th>
                <th>Actions</th>
            </tr>
        </thead>
    </table>
</div>

{{-- DataTables --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script>
(function () {
    let table = $('#logsTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        ajax: {
            url: "{{ route('login-logs.datatable') }}",
            data: function (d) {
                d.user_id = $('#filter_user').val();
                d.role    = $('#filter_role').val();
                d.from    = $('#filter_from').val();
                d.to      = $('#filter_to').val();
                d.status  = $('#filter_status').val();
            }
        },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'user', name: 'user.name', orderable: false },
            { data: 'role', orderable: false, searchable: false },
            { data: 'login_at', name: 'login_at' },
            { data: 'logout_at', name: 'logout_at' },
            { data: 'status_badge', orderable: false, searchable: false },
            { data: 'duration', orderable: false, searchable: false },
            { data: 'ip_address', name: 'ip' },
            { data: 'is_suspicious', name: 'is_suspicious' },
            { data: 'actions', orderable: false, searchable: false },
        ],
    });

    $('#applyFilters').on('click', function () {
        table.ajax.reload();
        updateExportLink();
    });

    $('#resetFilters').on('click', function () {
        $('#filter_user').val('');
        $('#filter_role').val('');
        $('#filter_from').val('');
        $('#filter_to').val('');
        $('#filter_status').val('');
        table.ajax.reload();
        updateExportLink();
    });

    function updateExportLink() {
        const params = new URLSearchParams();
        if ($('#filter_user').val()) params.append('user_id', $('#filter_user').val());
        if ($('#filter_from').val()) params.append('from', $('#filter_from').val());
        if ($('#filter_to').val()) params.append('to', $('#filter_to').val());

        const base = "{{ route('login-logs.export.csv') }}";
        document.getElementById('exportBtn').href = base + '?' + params.toString();
    }

    updateExportLink();
})();
</script>
@endsection
