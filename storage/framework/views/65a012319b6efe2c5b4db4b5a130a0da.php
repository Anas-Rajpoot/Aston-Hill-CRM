<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-xl shadow p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">Edit Expense</h2>
        <a href="<?php echo e(route('expenses.index')); ?>" class="text-sm text-gray-600 hover:underline">Back</a>
    </div>

    <form method="POST" action="<?php echo e(route('expenses.update', $expense)); ?>">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>
        <?php echo $__env->make('expenses._form', ['expense' => $expense], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <div class="mt-6 flex justify-end">
            <button class="bg-indigo-600 text-white px-4 py-2 rounded-md">Update</button>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\expenses\edit.blade.php ENDPATH**/ ?>