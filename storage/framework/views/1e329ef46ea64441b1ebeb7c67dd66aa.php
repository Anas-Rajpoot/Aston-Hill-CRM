<header class="h-16 bg-white border-b flex items-center justify-between px-4 sm:px-6">
    <!-- Left -->
    <div>
        <h1 class="text-sm font-semibold text-gray-800">
            <?php echo $__env->yieldContent('page-title', 'Dashboard'); ?>
        </h1>
        <p class="text-xs text-gray-500"><?php echo $__env->yieldContent('page-desc'); ?></p>
    </div>

    <!-- Right -->
    <div class="flex items-center gap-4 text-sm text-gray-700 relative">

        <?php
            $unreadCount = auth()->user()->unreadNotifications()->count();
            $badge = $unreadCount > 5 ? '5+' : $unreadCount;
            $topNotifications = auth()->user()->notifications()->latest()->limit(4)->get();
        ?>

        <div class="relative">
            <button id="notifBtn" type="button"
                class="relative p-2 rounded-lg hover:bg-gray-100 text-gray-700">
                
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0m6 0H9"/>
                </svg>

                <?php if($unreadCount > 0): ?>
                    <span class="absolute -top-1 -right-1 inline-flex items-center justify-center
                                text-[10px] min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white">
                        <?php echo e($badge); ?>

                    </span>
                <?php endif; ?>
            </button>

            
            <div id="notifDropdown"
                class="hidden absolute right-0 mt-2 w-80 bg-white border rounded-xl shadow-lg overflow-hidden z-50">
                <div class="px-4 py-3 flex items-center justify-between border-b">
                    <div>
                        <p class="font-semibold text-gray-800">Notifications</p>
                        <p class="text-xs text-gray-500">Latest updates & reminders</p>
                    </div>

                    <form method="POST" action="<?php echo e(route('notifications.readAll')); ?>">
                        <?php echo csrf_field(); ?>
                        <button class="text-xs text-indigo-600 hover:underline" type="submit">
                            Mark all read
                        </button>
                    </form>
                </div>

                <div class="max-h-80 overflow-auto">
                    <?php $__empty_1 = true; $__currentLoopData = $topNotifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $data = $n->data ?? [];
                            $title = $data['title'] ?? 'Notification';
                            $message = $data['message'] ?? '';
                            $url = $data['url'] ?? route('notifications.index');
                        ?>

                        <a href="<?php echo e($url); ?>" class="block px-4 py-3 border-b hover:bg-gray-50">
                            <p class="text-sm font-medium text-gray-800">
                                <?php echo e($title); ?>

                                <?php if(is_null($n->read_at)): ?>
                                    <span class="ml-2 text-[10px] px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">New</span>
                                <?php endif; ?>
                            </p>

                            <?php if($message): ?>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2"><?php echo e($message); ?></p>
                            <?php endif; ?>

                            <p class="text-[11px] text-gray-400 mt-1">
                                <?php echo e($n->created_at->format('d-M-Y h:i A')); ?>

                            </p>
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="px-4 py-6 text-sm text-gray-500">No notifications yet.</div>
                    <?php endif; ?>
                </div>

                <div class="px-4 py-3 bg-gray-50">
                    <a href="<?php echo e(route('notifications.index')); ?>" class="text-sm text-indigo-600 hover:underline">
                        See all notifications
                    </a>
                </div>
            </div>
        </div>

        
        <div><?php echo e(auth()->user()->name ?? 'Admin'); ?></div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('notifBtn');
    const dd  = document.getElementById('notifDropdown');

    btn?.addEventListener('click', function () {
        dd.classList.toggle('hidden');
    });

    document.addEventListener('click', function (e) {
        if (!dd.contains(e.target) && !btn.contains(e.target)) {
            dd.classList.add('hidden');
        }
    });

    const badgeEl = btn?.querySelector('span');
    const listEl = dd?.querySelector('.max-h-80');

    async function pollNotifications() {
        try {
            const res = await fetch("<?php echo e(route('notifications.poll')); ?>", {
                headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            if (!res.ok) return;

            const data = await res.json();

            // update badge
            if (data.unreadCount > 0) {
                if (!badgeEl) {
                    // if badge doesn't exist, reload page-level template is easiest,
                    // but for now: do nothing (optional enhancement)
                }
            }

            // easiest: just replace badge text if exists
            const currentBadge = btn.querySelector('span');
            if (data.unreadCount > 0) {
                if (currentBadge) currentBadge.textContent = data.badge;
            } else {
                if (currentBadge) currentBadge.remove();
            }

            // update list
            if (listEl) {
                if (!data.top || data.top.length === 0) {
                    listEl.innerHTML = `<div class="px-4 py-6 text-sm text-gray-500">No notifications yet.</div>`;
                } else {
                    listEl.innerHTML = data.top.map(n => `
                        <a href="${n.url}" class="block px-4 py-3 border-b hover:bg-gray-50">
                            <p class="text-sm font-medium text-gray-800">
                                ${escapeHtml(n.title)}
                                ${n.is_unread ? `<span class="ml-2 text-[10px] px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">New</span>` : ``}
                            </p>
                            ${n.message ? `<p class="text-xs text-gray-600 mt-1 line-clamp-2">${escapeHtml(n.message)}</p>` : ``}
                            <p class="text-[11px] text-gray-400 mt-1">${n.created_at}</p>
                        </a>
                    `).join('');
                }
            }
        } catch (e) {}
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, s => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[s]));
    }

    pollNotifications();
    setInterval(pollNotifications, 15000); // every 15 seconds
});
</script>

<?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\layouts\topbar.blade.php ENDPATH**/ ?>