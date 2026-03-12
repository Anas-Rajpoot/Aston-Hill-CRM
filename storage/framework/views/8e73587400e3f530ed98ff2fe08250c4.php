<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-2xl font-semibold mb-4">Edit User: <?php echo e($user->name); ?></h2>

    <form method="POST" action="<?php echo e(route('users.update', $user)); ?>">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <div class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="name" id="name" class="mt-1 block w-full" value="<?php echo e(old('name', $user->name)); ?>" required>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" id="email" class="mt-1 block w-full" value="<?php echo e(old('email', $user->email)); ?>" required>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                <input type="text" name="phone" id="phone" class="mt-1 block w-full" value="<?php echo e(old('phone', $user->phone)); ?>" required>
            </div>

            <div>
                <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                <input type="text" name="country" id="country" class="mt-1 block w-full" value="<?php echo e(old('country', $user->country)); ?>" required>
            </div>

            <div>
                <label for="cnic_number" class="block text-sm font-medium text-gray-700">CNIC Number</label>
                <input type="text" name="cnic_number" id="cnic_number" class="mt-1 block w-full" value="<?php echo e(old('cnic_number', $user->cnic_number)); ?>" required>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" id="password" class="mt-1 block w-full" placeholder="Leave empty if not changing">
            </div>

            <div>
                <label for="roles" class="block text-sm font-medium text-gray-700">Roles</label>
                <select name="roles[]" id="roles" multiple class="mt-1 block w-full">
                    <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($role->id); ?>" 
                            <?php echo e(in_array($role->id, old('roles', $user->roles->pluck('id')->toArray())) ? 'selected' : ''); ?>>
                            <?php echo e($role->name); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
        </div>

        <button type="submit" class="mt-4 bg-indigo-600 text-white px-4 py-2 rounded-md">Update User</button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\users\edit.blade.php ENDPATH**/ ?>