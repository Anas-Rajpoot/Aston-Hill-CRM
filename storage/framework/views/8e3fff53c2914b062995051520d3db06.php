<?php $__env->startSection('title', 'User Approval'); ?>

<?php $__env->startSection('page-title', 'User Approval'); ?>
<?php $__env->startSection('page-desc', 'Review user and update approval status and roles.'); ?>

<?php $__env->startSection('content'); ?>
<div class="bg-white border rounded-2xl shadow-sm overflow-hidden">

    <div class="px-6 py-5 border-b flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">User Approval</h2>
            <p class="text-sm text-gray-500">Update status and assign roles when approved.</p>
        </div>

        <a href="<?php echo e(route('users.index')); ?>"
           class="text-sm px-3 py-2 rounded-md border hover:bg-gray-50">
            Back to Users
        </a>
    </div>

    <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 rounded-xl bg-gray-50 border">
            <div class="text-xs text-gray-500">Name</div>
            <div class="font-medium text-gray-900"><?php echo e($user->name); ?></div>
        </div>

        <div class="p-4 rounded-xl bg-gray-50 border">
            <div class="text-xs text-gray-500">Email</div>
            <div class="font-medium text-gray-900 break-all"><?php echo e($user->email); ?></div>
        </div>

        <div class="p-4 rounded-xl bg-gray-50 border">
            <div class="text-xs text-gray-500">Status</div>
            <div class="font-medium text-gray-900"><?php echo e(ucfirst($user->status)); ?></div>
        </div>
    </div>

    <?php if($errors->any()): ?>
        <div class="mx-6 mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3">
            <ul class="list-disc pl-5 text-sm">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($err); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('super-admin.users.approve', $user->id)); ?>" class="px-6 pb-6">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                    <option value="">Select</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <div id="rejectReasonBox" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">Rejection Reason (optional)</label>
                <input type="text" name="rejection_reason"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="e.g. Missing documents">
            </div>
        </div>

        <div id="rolesBox" class="mt-5 hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">Assign Roles (Only if Approved)</label>

            <div class="border rounded-xl p-3 bg-gray-50 max-h-64 overflow-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="flex items-center gap-2 text-sm text-gray-800 bg-white border rounded-lg px-3 py-2">
                            <input type="checkbox" name="roles[]" value="<?php echo e($role->id); ?>"
                                   class="rounded border-gray-300">
                            <span><?php echo e($role->name); ?></span>
                        </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <a href="<?php echo e(route('users.index')); ?>"
               class="px-4 py-2 rounded-lg border text-sm hover:bg-gray-50">
                Cancel
            </a>

            <button type="submit"
                    class="px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                Save Changes
            </button>
        </div>
    </form>
</div>

<script>
    (function () {
        const status = document.getElementById('status');
        const rolesBox = document.getElementById('rolesBox');
        const rejectReasonBox = document.getElementById('rejectReasonBox');

        function toggle() {
            const v = status.value;
            rolesBox.classList.toggle('hidden', v !== 'approved');
            rejectReasonBox.classList.toggle('hidden', v !== 'rejected');
        }

        status.addEventListener('change', toggle);
        toggle();
    })();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\users\review.blade.php ENDPATH**/ ?>