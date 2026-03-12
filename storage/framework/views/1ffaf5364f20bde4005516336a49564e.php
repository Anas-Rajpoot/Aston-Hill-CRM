<?php $__env->startSection('content'); ?>
<?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <h2 class="text-xl font-semibold text-brand-dark">Lead Submissions</h2>
            <p class="text-sm text-gray-500">Filter, view and manage leads (wizard-based).</p>
        </div>

        <div class="flex gap-2">
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('lead_submissions.create')): ?>
                <a href="<?php echo e(route('lead-submissions.create.step1')); ?>" class="px-4 py-2 rounded bg-brand-primary text-white text-sm">
                    Add Lead Submission
                </a>
            <?php endif; ?>

            <button id="btnColumns" class="px-4 py-2 rounded bg-gray-900 text-white text-sm">
                Customize Columns
            </button>
        </div>
    </div>

    
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
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($category->id); ?>"><?php echo e($category->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>


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
            <?php
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
            ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php $__currentLoopData = $defaultCols; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="flex items-center gap-2 border rounded-lg p-3">
                        <input type="checkbox" class="colCheck rounded border-gray-300"
                               value="<?php echo e($col); ?>"
                               <?php if(in_array($col, $visibleCols)): echo 'checked'; endif; ?> />
                        <span class="text-sm text-gray-800"><?php echo e($labels[$col] ?? $col); ?></span>
                    </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <div class="flex justify-end gap-2 mt-5">
                <button id="btnCancelCols" class="px-4 py-2 rounded bg-gray-200 text-gray-900">Cancel</button>
                <button id="btnSaveCols" class="px-4 py-2 rounded bg-brand-primary text-white">Save</button>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
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
    let visibleCols = <?php echo json_encode($visibleCols, 15, 512) ?>;

    const table = $('#leadSubmissionTable').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        autoWidth: false,
        ajax: {
            url: "<?php echo e(route('lead-submissions.datatable')); ?>",
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

        const res = await fetch(`<?php echo e(route('lead-submissions.serviceTypesByCategory')); ?>?service_category_id=${catId}`);
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

        const res = await fetch("<?php echo e(route('lead-submissions.preferences.columns')); ?>", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "<?php echo e(csrf_token()); ?>"
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
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\lead-submission\index.blade.php ENDPATH**/ ?>