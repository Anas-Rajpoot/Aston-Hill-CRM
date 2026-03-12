<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold">Announcements</h2>
            <p class="text-sm text-gray-500">Latest updates, guides, and new features.</p>
        </div>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('announcements.create')): ?>
            <a href="<?php echo e(route('announcements.create')); ?>"
               class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm">Add Announcement</a>
        <?php endif; ?>
    </div>

    <div class="bg-gray-50 border rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <div>
                <label class="text-xs font-medium text-gray-600">Search</label>
                <input id="f_q" class="w-full border-gray-300 rounded-md" placeholder="title or text..." />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Active</label>
                <select id="f_active" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Pinned</label>
                <select id="f_pinned" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <option value="1">Pinned</option>
                    <option value="0">Not pinned</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Attachment</label>
                <select id="f_has_attachment" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <option value="1">Has attachment</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs font-medium text-gray-600">From</label>
                    <input id="f_from" type="date" class="w-full border-gray-300 rounded-md" />
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600">To</label>
                    <input id="f_to" type="date" class="w-full border-gray-300 rounded-md" />
                </div>
            </div>

            <div class="flex items-end gap-2">
                <button id="btn_apply" class="px-4 py-2 rounded bg-gray-800 text-white text-sm">Apply</button>
                <button id="btn_reset" class="px-4 py-2 rounded bg-gray-200 text-gray-900 text-sm">Reset</button>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table id="annTable" class="min-w-full text-sm">
            <thead class="bg-gray-100">
            <tr>
                <th class="p-2 text-left">Pinned</th>
                <th class="p-2 text-left">Title</th>
                <th class="p-2 text-left">Type</th>
                <th class="p-2 text-left">Status</th>
                <th class="p-2 text-left">Created</th>
                <th class="p-2 text-left">Created By</th>
                <th class="p-2 text-left">Actions</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const table = $('#annTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "<?php echo e(route('announcements.datatable')); ?>",
            data: function (d) {
                d.q = document.getElementById('f_q').value || '';
                d.active = document.getElementById('f_active').value || '';
                d.pinned = document.getElementById('f_pinned').value || '';
                d.has_attachment = document.getElementById('f_has_attachment').value || '';
                d.from = document.getElementById('f_from').value || '';
                d.to = document.getElementById('f_to').value || '';
            }
        },
        columns: [
            { data: 'is_pinned', name: 'is_pinned',
              render: (v) => v ? '📌' : '' },
            { data: 'title', name: 'title' },
            { data: 'type', orderable: false, searchable: false },
            { data: 'status', orderable: false, searchable: false },
            { data: 'created_at', name: 'created_at' },
            { data: 'created_by_name', orderable: false, searchable: false },
            { data: 'actions', orderable: false, searchable: false },
        ],
        order: [[4, 'desc']]
    });

    document.getElementById('btn_apply').addEventListener('click', () => table.ajax.reload());
    document.getElementById('btn_reset').addEventListener('click', () => {
        document.getElementById('f_q').value = '';
        document.getElementById('f_active').value = '';
        document.getElementById('f_pinned').value = '';
        document.getElementById('f_has_attachment').value = '';
        document.getElementById('f_from').value = '';
        document.getElementById('f_to').value = '';
        table.ajax.reload();
    });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\announcements\index.blade.php ENDPATH**/ ?>