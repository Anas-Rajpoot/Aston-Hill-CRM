<?php $__env->startSection('content'); ?>

<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold">Roles</h2>
            <?php if (isset($component)) { $__componentOriginal360d002b1b676b6f84d43220f22129e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal360d002b1b676b6f84d43220f22129e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.breadcrumbs','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('breadcrumbs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $attributes = $__attributesOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__attributesOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $component = $__componentOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__componentOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
        </div>

        <div class="flex items-center gap-2">
            <?php
                $trail = session('breadcrumbs_trail', []);
                $backUrl = count($trail) > 1 ? ($trail[count($trail)-2]['url'] ?? url()->previous()) : url()->previous();
            ?>

            <a href="<?php echo e($backUrl); ?>"
                class="text-sm text-gray-600 hover:text-indigo-600">
                ← Back
            </a>
            <a href="<?php echo e(route('super-admin.roles.create')); ?>"
            class="bg-indigo-600 text-white px-4 py-2 rounded-md">Add Role</a>
        </div>
    </div>
    <?php if(session('success')): ?>
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?>

    <?php if(session('error')): ?>
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <?php echo e(session('error')); ?>

        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left bg-gray-50">
                <tr>
                    <th class="p-3">Name</th>
                    <th class="p-3">Guard</th>
                    <th class="p-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="border-t">
                        <td class="p-3"><?php echo e($role->name); ?></td>
                        <td class="p-3"><?php echo e($role->guard_name); ?></td>
                        <td class="p-3">
                            <div class="flex justify-end gap-2">
                                <a class="px-3 py-1 rounded bg-blue-100 text-blue-700"
                                   href="<?php echo e(route('super-admin.roles.permissions.edit', $role)); ?>">
                                   Permissions
                                </a>

                                <a class="px-3 py-1 rounded bg-gray-100"
                                   href="<?php echo e(route('super-admin.roles.show', $role)); ?>">View</a>

                                <a class="px-3 py-1 rounded bg-indigo-100 text-indigo-700"
                                   href="<?php echo e(route('super-admin.roles.edit', $role)); ?>">Edit</a>

                                <form method="POST" action="<?php echo e(route('super-admin.roles.destroy', $role)); ?>"
                                      onsubmit="return confirm('Delete this role?')">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button class="px-3 py-1 rounded bg-red-100 text-red-700">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td class="p-3" colspan="3">No roles found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <?php echo e($roles->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\super-admin\roles\index.blade.php ENDPATH**/ ?>