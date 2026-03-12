<?php $__env->startSection('content'); ?>
<div class="max-w-5xl mx-auto bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold"><?php echo e($row->title); ?></h2>
            <p class="text-sm text-gray-500">
                Posted: <?php echo e($row->created_at?->format('d-M-Y h:i A')); ?>

                • By: <?php echo e($row->creator?->name); ?> (<?php echo e($row->creator?->email); ?>)
                <?php if($row->is_pinned): ?> • 📌 Pinned <?php endif; ?>
                <?php if(!$row->is_active): ?> • Inactive <?php endif; ?>
            </p>
        </div>

        <div class="flex gap-2">
            <a href="<?php echo e(route('announcements.index')); ?>" class="px-4 py-2 rounded-md bg-gray-100">Back</a>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('announcements.edit')): ?>
            <a href="<?php echo e(route('announcements.edit', $row)); ?>" class="px-4 py-2 rounded-md bg-indigo-600 text-white">Edit</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if($row->body): ?>
        <div class="prose max-w-none">
            <?php echo nl2br(e($row->body)); ?>

        </div>
    <?php else: ?>
        <p class="text-gray-500">—</p>
    <?php endif; ?>

    <?php if($row->attachment_path): ?>
        <div class="mt-6 p-4 bg-gray-50 border rounded-lg">
            <p class="text-sm font-medium mb-2">Attachment</p>

            <?php if(str_starts_with((string)$row->attachment_mime, 'image/')): ?>
                <img src="<?php echo e($row->attachment_url); ?>" class="max-h-[480px] rounded border" />
            <?php else: ?>
                <a href="<?php echo e($row->attachment_url); ?>" target="_blank"
                   class="text-indigo-600 hover:underline">
                    Download: <?php echo e($row->attachment_name); ?>

                </a>
                <p class="text-xs text-gray-500 mt-1">
                    <?php echo e($row->attachment_mime); ?> • <?php echo e(number_format(($row->attachment_size ?? 0)/1024, 2)); ?> KB
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('announcements.delete')): ?>
    <form method="POST" action="<?php echo e(route('announcements.destroy', $row)); ?>"
          onsubmit="return confirm('Delete this announcement?')" class="mt-6">
        <?php echo csrf_field(); ?>
        <?php echo method_field('DELETE'); ?>
        <button class="bg-red-600 text-white px-4 py-2 rounded-md">Delete</button>
    </form>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\announcements\show.blade.php ENDPATH**/ ?>