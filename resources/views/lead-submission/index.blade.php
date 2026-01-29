@extends('layouts.app')

@section('content')
<x-ui.card>
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <h2 class="text-xl font-semibold text-brand-dark">Lead Submissions</h2>
            <p class="text-sm text-gray-500">Filter, view and manage leads (wizard-based).</p>
        </div>

        <div class="flex gap-2">
            @can('lead_submissions.create')
                <a href="{{ route('lead-submissions.create.step1') }}" class="px-4 py-2 rounded bg-brand-primary text-white text-sm">
                    Add Lead Submission
                </a>
            @endcan

            <button id="btnColumns" class="px-4 py-2 rounded bg-gray-900 text-white text-sm">
                Customize Columns
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-brand-bg border rounded-xl p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="text-xs font-medium text-gray-600">Search</label>
                <input id="f_q" class="w-full border-gray-300 rounded-md" placeholder="Company, account, email..." />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Status</label>
                <select id="f_status" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Category</label>
                <select id="f_category" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Type</label>
                <select id="f_type" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">From</label>
                <input id="f_from" type="date" class="w-full border-gray-300 rounded-md"/>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">To</label>
                <input id="f_to" type="date" class="w-full border-gray-300 rounded-md"/>
            </div>

            <div class="flex items-end gap-2">
                <button id="btn_apply" class="px-4 py-2 rounded bg-gray-900 text-white text-sm">Apply</button>
                <button id="btn_reset" class="px-4 py-2 rounded bg-gray-200 text-gray-900 text-sm">Reset</button>
            </div>
        </div>
    </div>

    {{-- Table wrapper scroll --}}
    <div class="overflow-x-auto">
        <table id="leadSubmissionTable" class="min-w-full text-sm w-full">
            <thead class="bg-gray-100">
            <tr>
                <th class="p-2 text-left">Created</th>
                <th class="p-2 text-left">Lead Submission ID</th>
                <th class="p-2 text-left">Company</th>
                <th class="p-2 text-left">Account #</th>
                <th class="p-2 text-left">Request Type</th>
                <th class="p-2 text-left">Category</th>
                <th class="p-2 text-left">Type</th>
                <th class="p-2 text-left">Status</th>
                <th class="p-2 text-left">Created By</th>
                <th class="p-2 text-left">Email</th>
                <th class="p-2 text-left">Phone</th>
                <th class="p-2 text-left">Actions</th>
            </tr>
            </thead>
        </table>
    </div>
</x-ui.card>

{{-- Customize Columns Modal --}}
<div id="columnsModal" class="hidden fixed inset-0 bg-black/40 z-50">
    <div class="bg-white rounded-xl shadow-lg w-[95%] max-w-xl mx-auto mt-20 overflow-hidden">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div>
                <p class="font-semibold text-gray-800">Customize Columns</p>
                <p class="text-xs text-gray-500">Select the columns you want to see.</p>
            </div>
            <button id="btnCloseModal" class="text-gray-500 hover:text-gray-800">✕</button>
        </div>

        <div class="p-5">
            @php
                $labels = [
                    'created_at'=>'Created',
                    'lead_submission_id'=>'Lead Submission ID',
                    'company_name'=>'Company',
                    'account_number'=>'Account #',
                    'request_type'=>'Request Type',
                    'category'=>'Category',
                    'type'=>'Type',
                    'status'=>'Status',
                    'created_by'=>'Created By',
                    'email'=>'Email',
                    'phone'=>'Phone',
                ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($defaultCols as $col)
                    <label class="flex items-center gap-2 border rounded-lg p-3">
                        <input type="checkbox" class="colCheck rounded border-gray-300"
                               value="{{ $col }}"
                               @checked(in_array($col, $visibleCols)) />
                        <span class="text-sm text-gray-800">{{ $labels[$col] ?? $col }}</span>
                    </label>
                @endforeach
            </div>

            <div class="flex justify-end gap-2 mt-5">
                <button id="btnCancelCols" class="px-4 py-2 rounded bg-gray-200 text-gray-900">Cancel</button>
                <button id="btnSaveCols" class="px-4 py-2 rounded bg-brand-primary text-white">Save</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {

    // Mapping between our column keys and datatable indexes
    const colIndexMap = {
        created_at: 0,
        lead_submission_id: 1,
        company_name: 2,
        account_number: 3,
        request_type: 4,
        category: 5,
        type: 6,
        status: 7,
        created_by: 8,
        email: 9,
        phone: 10,
    };

    // initial visible columns from backend
    let visibleCols = @json($visibleCols);

    const table = $('#leadSubmissionTable').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('lead-submissions.datatable') }}",
            data: function (d) {
                d.q = document.getElementById('f_q').value || '';
                d.status = document.getElementById('f_status').value || '';
                d.service_category_id = document.getElementById('f_category').value || '';
                d.service_type_id = document.getElementById('f_type').value || '';
                d.from = document.getElementById('f_from').value || '';
                d.to = document.getElementById('f_to').value || '';
            }
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'lead_submission_id', name: 'id' },
            { data: 'company_name', name: 'company_name' },
            { data: 'account_number', name: 'account_number' },
            { data: 'request_type', name: 'request_type', defaultContent:'-' },
            { data: 'category', orderable:false, searchable:false },
            { data: 'type', orderable:false, searchable:false },
            { data: 'status', name: 'status' },
            { data: 'created_by', orderable:false, searchable:false },
            { data: 'email', name: 'email', defaultContent:'-' },
            { data: 'contact_number', name: 'contact_number', defaultContent:'-' },
            { data: 'actions', orderable:false, searchable:false }
        ],
        order: [[0,'desc']],
        initComplete: function () {
            applyColumnVisibility();
        }
    });

    function applyColumnVisibility(){
        // default hide all data columns then show checked; keep Actions always visible
        Object.keys(colIndexMap).forEach(k => {
            const idx = colIndexMap[k];
            const shouldShow = visibleCols.includes(k);
            table.column(idx).visible(shouldShow);
        });
        table.columns.adjust().draw(false);
    }

    // Filters actions
    document.getElementById('btn_apply').addEventListener('click', () => table.ajax.reload());

    document.getElementById('btn_reset').addEventListener('click', () => {
        document.getElementById('f_q').value = '';
        document.getElementById('f_status').value = '';
        document.getElementById('f_category').value = '';
        document.getElementById('f_type').innerHTML = '<option value="">All</option>';
        document.getElementById('f_from').value = '';
        document.getElementById('f_to').value = '';
        table.ajax.reload();
    });

    // Load types dropdown when category changes
    document.getElementById('f_category').addEventListener('change', async function () {
        const catId = this.value;
        const typeSel = document.getElementById('f_type');
        typeSel.innerHTML = '<option value="">All</option>';

        if(!catId) return;

        const res = await fetch(`{{ route('lead-submissions.serviceTypesByCategory') }}?service_category_id=${catId}`);
        const rows = await res.json();
        rows.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = r.name;
            typeSel.appendChild(opt);
        });
    });

    // Modal
    const modal = document.getElementById('columnsModal');
    const openBtn = document.getElementById('btnColumns');
    const closeBtn = document.getElementById('btnCloseModal');
    const cancelBtn = document.getElementById('btnCancelCols');
    const saveBtn = document.getElementById('btnSaveCols');

    function openModal(){ modal.classList.remove('hidden'); }
    function closeModal(){ modal.classList.add('hidden'); }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    saveBtn.addEventListener('click', async function () {
        const checks = document.querySelectorAll('.colCheck:checked');
        const cols = Array.from(checks).map(c => c.value);

        if(cols.length < 1){
            alert('Select at least 1 column.');
            return;
        }

        const res = await fetch("{{ route('lead-submissions.preferences.columns') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({ columns: cols })
        });

        const json = await res.json();
        if(json.ok){
            visibleCols = cols;
            applyColumnVisibility();
            closeModal();
        } else {
            alert('Failed to save preferences.');
        }
    });

});
</script>
@endpush
