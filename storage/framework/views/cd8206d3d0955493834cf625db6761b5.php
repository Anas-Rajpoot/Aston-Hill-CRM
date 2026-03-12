<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-xl shadow p-6 max-w-xl">
    <h2 class="text-xl font-semibold mb-4">Create Role</h2>

    <form method="POST" action="<?php echo e(route('super-admin.roles.store')); ?>">
        <?php echo csrf_field(); ?>

        <label class="block font-medium mb-1">Role Name</label>
        <input name="name" value="<?php echo e(old('name')); ?>"
               class="w-full border rounded-md px-3 py-2" placeholder="e.g. manager" required>

        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-red-600 text-sm mt-2"><?php echo e($message); ?></p>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <div class="mt-4 flex gap-2">
            <button class="bg-indigo-600 text-white px-4 py-2 rounded-md">Save</button>
            <a href="<?php echo e(route('super-admin.roles.index')); ?>" class="px-4 py-2 rounded-md bg-gray-100">Cancel</a>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\super-admin\roles\create.blade.php ENDPATH**/ ?>