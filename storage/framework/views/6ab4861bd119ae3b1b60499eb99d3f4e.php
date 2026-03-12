<?php $__env->startSection('content'); ?>
<h2>Permission Details</h2>
<p><b>ID:</b> <?php echo e($permission->id); ?></p>
<p><b>Name:</b> <?php echo e($permission->name); ?></p>
<a href="<?php echo e(route('super-admin.permissions.index')); ?>">Back</a>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\super-admin\permissions\show.blade.php ENDPATH**/ ?>