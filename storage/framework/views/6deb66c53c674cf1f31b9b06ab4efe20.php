<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold">Email Follow Up</h2>
            <p class="text-sm text-gray-500">Track follow-up emails with filters and export.</p>
        </div>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('emails_followup.create')): ?>
            <a href="<?php echo e(route('email-followups.create')); ?>"
               class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm">Add Entry</a>
        <?php endif; ?>
    </div>

    
    <div class="bg-gray-50 border rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">

            <?php if(auth()->user()->hasRole('superadmin')): ?>
            <div>
                <label class="text-xs font-medium text-gray-600">Created By</label>
                <select id="f_created_by" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <?php $__currentLoopData = $creators; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($u->id); ?>"><?php echo e($u->name); ?> (<?php echo e($u->email); ?>)</option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <?php endif; ?>

            <div>
                <label class="text-xs font-medium text-gray-600">Category</label>
                <select id="f_category" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($c); ?>"><?php echo e($c); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Subject</label>
                <input id="f_subject" class="w-full border-gray-300 rounded-md" placeholder="Search subject..." />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Request From</label>
                <input id="f_request_from" class="w-full border-gray-300 rounded-md" placeholder="Name / company..." />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Sent To</label>
                <input id="f_sent_to" class="w-full border-gray-300 rounded-md" placeholder="Email / person..." />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Preset</label>
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

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('emails_followup.list')): ?>
            <div class="flex items-end">
                <a id="btn_export" href="#"
                   class="px-4 py-2 rounded bg-emerald-600 text-white text-sm w-full text-center">
                    Export CSV
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <div class="overflow-x-auto">
        <table id="emailFollowupsTable" class="min-w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">Email Date</th>
                    <th class="p-2 text-left">Subject</th>
                    <th class="p-2 text-left">Category</th>
                    <th class="p-2 text-left">Request From</th>
                    <th class="p-2 text-left">Sent To</th>
                    <th class="p-2 text-left">Created By</th>
                    <th class="p-2 text-left">Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const table = $('#emailFollowupsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "<?php echo e(route('email-followups.datatable')); ?>",
            data: function (d) {
                d.created_by   = document.getElementById('f_created_by')?.value || '';
                d.category     = document.getElementById('f_category').value || '';
                d.subject      = document.getElementById('f_subject').value || '';
                d.request_from = document.getElementById('f_request_from').value || '';
                d.sent_to      = document.getElementById('f_sent_to').value || '';
                d.preset       = document.getElementById('f_preset').value || '';
                d.from         = document.getElementById('f_from').value || '';
                d.to           = document.getElementById('f_to').value || '';
            }
        },
        columns: [
            { data: 'email_date', name: 'email_date' },
            { data: 'subject', name: 'subject' },
            { data: 'category', name: 'category' },
            { data: 'request_from', name: 'request_from', defaultContent: '—' },
            { data: 'sent_to', name: 'sent_to', defaultContent: '—' },
            { data: 'created_by_name', orderable: false, searchable: false },
            { data: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']]
    });

    document.getElementById('btn_apply').addEventListener('click', () => table.ajax.reload());

    document.getElementById('btn_reset').addEventListener('click', () => {
        document.getElementById('f_created_by')?.value && (document.getElementById('f_created_by').value = '');
        document.getElementById('f_category').value = '';
        document.getElementById('f_subject').value = '';
        document.getElementById('f_request_from').value = '';
        document.getElementById('f_sent_to').value = '';
        document.getElementById('f_preset').value = 'this_month';
        document.getElementById('f_from').value = '';
        document.getElementById('f_to').value = '';
        table.ajax.reload();
    });

    function buildExportUrl() {
        const base = "<?php echo e(route('email-followups.export.csv')); ?>";
        const params = new URLSearchParams();

        const createdBy = document.getElementById('f_created_by')?.value || '';
        if (createdBy) params.set('created_by', createdBy);

        const category = document.getElementById('f_category').value || '';
        if (category) params.set('category', category);

        const subject = document.getElementById('f_subject').value || '';
        if (subject) params.set('subject', subject);

        const requestFrom = document.getElementById('f_request_from').value || '';
        if (requestFrom) params.set('request_from', requestFrom);

        const sentTo = document.getElementById('f_sent_to').value || '';
        if (sentTo) params.set('sent_to', sentTo);

        const preset = document.getElementById('f_preset').value || '';
        const from = document.getElementById('f_from').value || '';
        const to = document.getElementById('f_to').value || '';

        if (from || to) {
            if (from) params.set('from', from);
            if (to) params.set('to', to);
        } else {
            params.set('preset', preset);
        }

        return base + '?' + params.toString();
    }

    const exportBtn = document.getElementById('btn_export');
    exportBtn.addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = buildExportUrl();
    });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\email-followups\index.blade.php ENDPATH**/ ?>