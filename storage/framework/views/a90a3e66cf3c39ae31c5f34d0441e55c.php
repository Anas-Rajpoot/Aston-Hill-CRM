<?php $__env->startSection('title', 'User Details'); ?>
<?php $__env->startSection('page-title', 'User Details'); ?>
<?php $__env->startSection('page-desc', 'View user info and status.'); ?>

<?php $__env->startSection('content'); ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('accounts.create')): ?>
    <a href="<?php echo e(route('accounts.create')); ?>">Add Account</a>
    <?php endif; ?>

    <?php $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('accounts.show', $account)); ?>">View</a>

    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $account)): ?>
        <a href="<?php echo e(route('accounts.edit', $account)); ?>">Edit</a>
    <?php endif; ?>

    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete', $account)): ?>
        <form method="POST" action="<?php echo e(route('accounts.destroy', $account)); ?>">
        <?php echo csrf_field(); ?>
        <?php echo method_field('DELETE'); ?>
        <button type="submit">Delete</button>
        </form>
    <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\accounts\index.blade.php ENDPATH**/ ?>