@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold">Personal Notes</h2>
            <p class="text-sm text-gray-500">Your private to-do list and notes.</p>
        </div>

        @can('personal_notes.create')
            <a href="{{ route('personal-notes.create') }}"
               class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm">Add Note</a>
        @endcan
    </div>

    <div class="bg-gray-50 border rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <div>
                <label class="text-xs font-medium text-gray-600">Search</label>
                <input id="f_q" class="w-full border-gray-300 rounded-md" placeholder="title or body..." />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Status</label>
                <select id="f_status" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="done">Done</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Priority</label>
                <select id="f_priority" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
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

            <div class="flex items-end gap-2 md:col-span-2">
                <button id="btn_apply" class="px-4 py-2 rounded bg-gray-800 text-white text-sm">Apply</button>
                <button id="btn_reset" class="px-4 py-2 rounded bg-gray-200 text-gray-900 text-sm">Reset</button>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table id="notesTable" class="min-w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">Created</th>
                    <th class="p-2 text-left">Title</th>
                    <th class="p-2 text-left">Status</th>
                    <th class="p-2 text-left">Priority</th>
                    <th class="p-2 text-left">Due</th>
                    <th class="p-2 text-left">Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // IMPORTANT: DataTables needs jQuery.
    // If you see "$ is not defined", add jQuery + datatables scripts in your layout.
    const table = $('#notesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('personal-notes.datatable') }}",
            data: function (d) {
                d.q = document.getElementById('f_q').value || '';
                d.status = document.getElementById('f_status').value || '';
                d.priority = document.getElementById('f_priority').value || '';
                d.from = document.getElementById('f_from').value || '';
                d.to = document.getElementById('f_to').value || '';
            }
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'title', name: 'title' },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'priority_badge', name: 'priority', orderable: false, searchable: false },
            { data: 'due_date', name: 'due_date' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']]
    });

    document.getElementById('btn_apply').addEventListener('click', () => table.ajax.reload());

    document.getElementById('btn_reset').addEventListener('click', () => {
        document.getElementById('f_q').value = '';
        document.getElementById('f_status').value = '';
        document.getElementById('f_priority').value = '';
        document.getElementById('f_from').value = '';
        document.getElementById('f_to').value = '';
        table.ajax.reload();
    });
});
</script>
@endsection
