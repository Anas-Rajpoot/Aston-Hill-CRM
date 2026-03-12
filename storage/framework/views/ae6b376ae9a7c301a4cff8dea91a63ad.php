<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-xl shadow p-6 max-w-4xl mx-auto">
    <div class="flex items-start justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-semibold"><?php echo e($note->title); ?></h2>
            <p class="text-sm text-gray-500">
                Created: <?php echo e($note->created_at?->format('d-M-Y h:i A')); ?>

                <?php if($note->due_date): ?>
                    • Due: <?php echo e($note->due_date->format('d-M-Y')); ?>

                <?php endif; ?>
            </p>
        </div>

        <div class="flex gap-2 flex-wrap justify-end">
            <a href="<?php echo e(route('personal-notes.index')); ?>"
               class="px-4 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">
                Back
            </a>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('personal_notes.edit')): ?>
            <a href="<?php echo e(route('personal-notes.edit', $note)); ?>"
               class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                Edit
            </a>

            <form method="POST" action="<?php echo e(route('personal-notes.toggle', $note)); ?>">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>
                <button class="px-4 py-2 rounded-md bg-emerald-600 text-white text-sm hover:bg-emerald-700">
                    <?php echo e($note->status === 'done' ? 'Mark Pending' : 'Mark Done'); ?>

                </button>
            </form>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('personal_notes.delete')): ?>
            <form method="POST" action="<?php echo e(route('personal-notes.destroy', $note)); ?>"
                  onsubmit="return confirm('Delete this note?')">
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <button class="px-4 py-2 rounded-md bg-red-600 text-white text-sm hover:bg-red-700">
                    Delete
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="border rounded-lg p-4">
            <p class="text-xs text-gray-500 mb-1">Status</p>
            <p class="text-sm font-semibold">
                <?php echo e($note->status === 'done' ? 'Done' : 'Pending'); ?>

            </p>
            <?php if($note->completed_at): ?>
                <p class="text-xs text-gray-500 mt-2">Completed:</p>
                <p class="text-sm"><?php echo e($note->completed_at->format('d-M-Y h:i A')); ?></p>
            <?php endif; ?>
        </div>

        <div class="border rounded-lg p-4">
            <p class="text-xs text-gray-500 mb-1">Priority</p>
            <p class="text-sm font-semibold"><?php echo e(ucfirst($note->priority)); ?></p>
        </div>

        <div class="border rounded-lg p-4">
            <p class="text-xs text-gray-500 mb-1">Due Date</p>
            <p class="text-sm font-semibold"><?php echo e($note->due_date?->format('d-M-Y') ?? '—'); ?></p>
        </div>
    </div>

    <div class="border rounded-lg p-4 bg-gray-50">
        <p class="text-xs text-gray-500 mb-2">Details</p>
        <?php if($note->body): ?>
            <div class="text-sm text-gray-800 whitespace-pre-line"><?php echo e($note->body); ?></div>
        <?php else: ?>
            <p class="text-sm text-gray-500">No details added.</p>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\personal-notes\show.blade.php ENDPATH**/ ?>