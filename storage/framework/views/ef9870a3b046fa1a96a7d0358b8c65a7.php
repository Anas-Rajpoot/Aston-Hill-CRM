<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">Add Announcement</h2>
        <a href="<?php echo e(route('announcements.index')); ?>" class="text-sm text-gray-600 hover:underline">Back</a>
    </div>

    <form method="POST" action="<?php echo e(route('announcements.store')); ?>" enctype="multipart/form-data" class="space-y-4">
        <?php echo csrf_field(); ?>

        <div>
            <label class="text-sm font-medium">Title</label>
            <input name="title" value="<?php echo e(old('title')); ?>" class="w-full border-gray-300 rounded-md" required>
            <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-sm text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div>
            <label class="text-sm font-medium">Text</label>
            <textarea name="body" rows="6" class="w-full border-gray-300 rounded-md"
                placeholder="Explain new feature / how to use..."><?php echo e(old('body')); ?></textarea>
            <?php $__errorArgs = ['body'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-sm text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="text-sm font-medium">Attachment (image/file)</label>
                <input type="file" name="attachment" class="w-full border-gray-300 rounded-md">
                <p class="text-xs text-gray-500 mt-1">Max 10MB</p>
                <?php $__errorArgs = ['attachment'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-sm text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div>
                <label class="text-sm font-medium">Publish At (optional)</label>
                <input type="datetime-local" name="published_at" class="w-full border-gray-300 rounded-md"
                       value="<?php echo e(old('published_at')); ?>">
                <?php $__errorArgs = ['published_at'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-sm text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_pinned" value="1" class="rounded">
                <span class="text-sm">Pinned</span>
            </label>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" class="rounded" checked>
                <span class="text-sm">Active</span>
            </label>
        </div>

        <div class="flex gap-2">
            <button class="bg-indigo-600 text-white px-4 py-2 rounded-md">Save</button>
            <a href="<?php echo e(route('announcements.index')); ?>" class="px-4 py-2 rounded-md bg-gray-100">Cancel</a>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\announcements\create.blade.php ENDPATH**/ ?>