<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-xl shadow p-6 max-w-xl">
    <h2 class="text-xl font-semibold mb-4">Role Details</h2>

    <p><strong>Name:</strong> <?php echo e($role->name); ?></p>
    <p><strong>Guard:</strong> <?php echo e($role->guard_name); ?></p>

    <div class="mt-4 flex gap-2">
        <a href="<?php echo e(route('super-admin.roles.edit', $role)); ?>" class="bg-indigo-600 text-white px-4 py-2 rounded-md">Edit</a>
        <a href="<?php echo e(route('super-admin.roles.index')); ?>" class="px-4 py-2 rounded-md bg-gray-100">Back</a>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\super-admin\roles\show.blade.php ENDPATH**/ ?>