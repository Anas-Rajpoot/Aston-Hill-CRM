<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-xl shadow p-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-semibold">Edit Note</h2>
            <p class="text-sm text-gray-500">Update your personal note or to-do.</p>
        </div>

        <a href="<?php echo e(route('personal-notes.show', $note)); ?>"
           class="px-4 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">
            Back
        </a>
    </div>

    <?php if($errors->any()): ?>
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">
            <ul class="list-disc pl-5">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($err); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('personal-notes.update', $note)); ?>" class="space-y-4">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <div>
            <label class="block text-sm font-medium text-gray-700">Title</label>
            <input name="title" value="<?php echo e(old('title', $note->title)); ?>"
                   class="mt-1 w-full border-gray-300 rounded-md shadow-sm"
                   required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Details (optional)</label>
            <textarea name="body" rows="6"
                      class="mt-1 w-full border-gray-300 rounded-md shadow-sm"
                      placeholder="Write details here..."><?php echo e(old('body', $note->body)); ?></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                    <option value="pending" <?php echo e(old('status', $note->status) === 'pending' ? 'selected' : ''); ?>>Pending</option>
                    <option value="done" <?php echo e(old('status', $note->status) === 'done' ? 'selected' : ''); ?>>Done</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Priority</label>
                <select name="priority" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                    <option value="low" <?php echo e(old('priority', $note->priority) === 'low' ? 'selected' : ''); ?>>Low</option>
                    <option value="medium" <?php echo e(old('priority', $note->priority) === 'medium' ? 'selected' : ''); ?>>Medium</option>
                    <option value="high" <?php echo e(old('priority', $note->priority) === 'high' ? 'selected' : ''); ?>>High</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Due Date</label>
                <input type="date" name="due_date"
                       value="<?php echo e(old('due_date', optional($note->due_date)->format('Y-m-d'))); ?>"
                       class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
            </div>
        </div>

        <?php if($note->completed_at): ?>
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">
                Completed at: <strong><?php echo e($note->completed_at->format('d-M-Y h:i A')); ?></strong>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-end gap-2 pt-2">
            <a href="<?php echo e(route('personal-notes.show', $note)); ?>"
               class="px-4 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">
                Cancel
            </a>

            <button type="submit"
                    class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                Update Note
            </button>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\personal-notes\edit.blade.php ENDPATH**/ ?>