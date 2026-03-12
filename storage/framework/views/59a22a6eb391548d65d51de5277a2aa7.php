<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-2xl font-semibold mb-4"><?php echo e($user->name); ?></h2>

    <div class="space-y-4">
        <p><strong>Email:</strong> <?php echo e($user->email); ?></p>
        <p><strong>Phone:</strong> <?php echo e($user->phone); ?></p>
        <p><strong>Country:</strong> <?php echo e($user->country); ?></p>
        <p><strong>CNIC Number:</strong> <?php echo e($user->cnic_number); ?></p>
        <p><strong>Status:</strong> <?php echo e(ucfirst($user->status)); ?></p>
    </div>

    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('users.edit')): ?>
        <div class="mt-4">
            <a href="<?php echo e(route('users.edit', $user)); ?>" class="text-indigo-600">Edit User</a>
        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\users\show.blade.php ENDPATH**/ ?>