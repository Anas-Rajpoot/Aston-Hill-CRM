<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-semibold">Notifications</h2>
            <p class="text-sm text-gray-500">All announcements and reminders.</p>
        </div>

        <form method="POST" action="<?php echo e(route('notifications.readAll')); ?>">
            <?php echo csrf_field(); ?>
            <button class="px-4 py-2 rounded bg-gray-900 text-white text-sm">
                Mark all read
            </button>
        </form>
    </div>

    
    <form class="bg-gray-50 border rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="text-xs font-medium text-gray-600">Type</label>
                <select name="kind" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <option value="announcement" <?php if(request('kind')==='announcement'): echo 'selected'; endif; ?>>Announcements</option>
                    <option value="email_followup" <?php if(request('kind')==='email_followup'): echo 'selected'; endif; ?>>Email Follow Ups</option>
                    <option value="personal_note" <?php if(request('kind')==='personal_note'): echo 'selected'; endif; ?>>Personal Notes</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Status</label>
                <select name="status" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <option value="unread" <?php if(request('status')==='unread'): echo 'selected'; endif; ?>>Unread</option>
                    <option value="read" <?php if(request('status')==='read'): echo 'selected'; endif; ?>>Read</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button class="px-4 py-2 rounded bg-gray-800 text-white text-sm">Apply</button>
                <a href="<?php echo e(route('notifications.index')); ?>" class="px-4 py-2 rounded bg-gray-200 text-gray-900 text-sm">
                    Reset
                </a>
            </div>
        </div>
    </form>

    <div class="divide-y border rounded-xl overflow-hidden">
        <?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                $data = $n->data ?? [];
                $title = $data['title'] ?? 'Notification';
                $message = $data['message'] ?? '';
                $url = $data['url'] ?? null;
            ?>

            <div class="p-4 flex items-start justify-between gap-4 <?php echo e(is_null($n->read_at) ? 'bg-indigo-50/40' : 'bg-white'); ?>">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-gray-800"><?php echo e($title); ?></p>
                        <?php if(is_null($n->read_at)): ?>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">Unread</span>
                        <?php endif; ?>
                    </div>

                    <?php if($message): ?>
                        <p class="text-sm text-gray-600 mt-1"><?php echo e($message); ?></p>
                    <?php endif; ?>

                    <p class="text-xs text-gray-400 mt-2"><?php echo e($n->created_at->format('d-M-Y h:i A')); ?></p>

                    <?php if($url): ?>
                        <a href="<?php echo e($url); ?>" class="text-sm text-indigo-600 hover:underline mt-2 inline-block">Open</a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <?php if(is_null($n->read_at)): ?>
                        <form method="POST" action="<?php echo e(route('notifications.read', $n->id)); ?>">
                            <?php echo csrf_field(); ?>
                            <button class="px-3 py-1 rounded bg-gray-900 text-white text-xs">Mark read</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?php echo e(route('notifications.unread', $n->id)); ?>">
                            <?php echo csrf_field(); ?>
                            <button class="px-3 py-1 rounded bg-gray-200 text-gray-900 text-xs">Mark unread</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="p-6 text-sm text-gray-500">No notifications found.</div>
        <?php endif; ?>
    </div>

    <div class="mt-4">
        <?php echo e($notifications->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\notifications\index.blade.php ENDPATH**/ ?>