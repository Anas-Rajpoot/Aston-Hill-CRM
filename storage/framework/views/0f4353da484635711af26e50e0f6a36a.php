<?php $__env->startSection('content'); ?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold">Activity Timeline</h1>
        <p class="text-sm text-gray-500"><?php echo e($user->name); ?> (<?php echo e($user->email); ?>)</p>
    </div>
    <a class="px-4 py-2 rounded bg-gray-800 text-white text-sm" href="<?php echo e(route('login-logs.index')); ?>">
        Back
    </a>
</div>

<div class="bg-white rounded-lg shadow p-4">
    <div class="space-y-3">
        <?php $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="border rounded p-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium">
                        Login: <?php echo e($log->login_at?->format('d-M-Y h:i A')); ?>

                        <span class="text-gray-500">|</span>
                        Logout: <?php echo e($log->logout_at?->format('d-M-Y h:i A') ?? '— (Online)'); ?>

                    </div>
                    <div class="text-xs px-2 py-1 rounded <?php echo e($log->logout_at ? 'bg-gray-200 text-gray-800' : 'bg-green-100 text-green-700'); ?>">
                        <?php echo e($log->logout_at ? 'Offline' : 'Online'); ?>

                    </div>
                </div>

                <div class="mt-2 text-xs text-gray-600">
                    IP: <?php echo e($log->ip ?? '-'); ?> |
                    Country: <?php echo e($log->country ?? '-'); ?> |
                    Suspicious: <?php echo e($log->is_suspicious ? 'YES' : 'NO'); ?>

                    <?php if($log->suspicious_reason): ?>
                        | Reason: <?php echo e($log->suspicious_reason); ?>

                    <?php endif; ?>
                </div>

                <div class="mt-2 text-xs text-gray-700">
                    Active Seconds: <?php echo e($log->active_seconds); ?>

                </div>

                <?php if(!$log->logout_at): ?>
                    <div class="mt-3 flex gap-2">
                        <form method="POST" action="<?php echo e(route('login-logs.force-logout-log', $log->id)); ?>"
                              onsubmit="return confirm('Force logout this session?')">
                            <?php echo csrf_field(); ?>
                            <button class="px-3 py-1 rounded bg-red-600 text-white text-xs">Force Logout (Session)</button>
                        </form>

                        <form method="POST" action="<?php echo e(route('login-logs.force-logout-user', $user->id)); ?>"
                              onsubmit="return confirm('Force logout user from all sessions?')">
                            <?php echo csrf_field(); ?>
                            <button class="px-3 py-1 rounded bg-red-800 text-white text-xs">Force Logout (User)</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div class="mt-4">
        <?php echo e($logs->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\login-logs\timeline.blade.php ENDPATH**/ ?>