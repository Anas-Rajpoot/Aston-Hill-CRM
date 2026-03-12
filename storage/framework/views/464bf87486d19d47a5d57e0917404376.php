<!-- <aside class="w-64 bg-white border-r hidden md:flex md:flex-col">
    <div class="h-16 flex items-center px-6 border-b">
        <span class="text-lg font-semibold text-gray-800">Super Admin</span>
    </div>

    <nav class="p-4 space-y-1">
        <a href="<?php echo e(route('super-admin.dashboard')); ?>"
           class="block px-3 py-2 rounded-md text-sm font-medium
           <?php echo e(request()->routeIs('super-admin.dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'); ?>">
            Dashboard
        </a>

        <?php if (\Illuminate\Support\Facades\Blade::check('role', 'superadmin')): ?>
            <a href="<?php echo e(route('users.index')); ?>"
                class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                <?php echo e(request()->routeIs('users.*')
                    ? 'bg-indigo-50 text-indigo-700'
                    : 'text-gray-700 hover:bg-gray-50'); ?>">
                    👥 <span>Users</span>
            </a>
        <?php endif; ?>

        <?php if (\Illuminate\Support\Facades\Blade::check('role', 'superadmin')): ?>
            <a href="<?php echo e(route('super-admin.roles.index')); ?>"
                class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                <?php echo e(request()->routeIs('super-admin.roles.*')
                    ? 'bg-indigo-50 text-indigo-700'
                    : 'text-gray-700 hover:bg-gray-50'); ?>">
                     <span>Roles</span>
            </a>
        <?php endif; ?>

        <?php if (\Illuminate\Support\Facades\Blade::check('role', 'superadmin')): ?>
            <a href="<?php echo e(route('super-admin.permissions.index')); ?>"
                class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                <?php echo e(request()->routeIs('super-admin.permissions.*')
                    ? 'bg-indigo-50 text-indigo-700'
                    : 'text-gray-700 hover:bg-gray-50'); ?>">
                    <span>Permissions</span>
            </a>
        <?php endif; ?>

        <a href="<?php echo e(route('lead-submissions.index')); ?>"
            class="block px-3 py-2 rounded-md text-sm font-medium
            <?php echo e(request()->routeIs('lead-submissions.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'); ?>">
                Lead Submissions
        </a>

        <a href="<?php echo e(route('announcements.index')); ?>"
            class="block px-3 py-2 rounded-md text-sm font-medium
            <?php echo e(request()->routeIs('announcements.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'); ?>">
                Announcements
        </a>

        <a href="<?php echo e(route('notifications.index')); ?>"
            class="block px-3 py-2 rounded-md text-sm font-medium
            <?php echo e(request()->routeIs('notifications.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'); ?>">
                Notifications
        </a>


        <a href="<?php echo e(route('accounts.index')); ?>"
           class="block px-3 py-2 rounded-md text-sm font-medium
           <?php echo e(request()->routeIs('accounts.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'); ?>">
            Account
        </a>

        <a href="<?php echo e(route('expenses.index')); ?>"
            class="block px-3 py-2 rounded-md text-sm font-medium
            <?php echo e(request()->routeIs('expenses.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'); ?>">
            Expenses
        </a>

        <a href="<?php echo e(route('personal-notes.index')); ?>"
            class="block px-3 py-2 rounded-md text-sm font-medium
            <?php echo e(request()->routeIs('personal-notes.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'); ?>">
                Personal Notes
        </a>

        <a href="<?php echo e(route('email-followups.index')); ?>"
            class="block px-3 py-2 rounded-md text-sm font-medium
            <?php echo e(request()->routeIs('email-followups.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'); ?>">
            Email Follow Up
        </a>


        <a href="<?php echo e(route('login-logs.index')); ?>"
           class="block px-3 py-2 rounded-md text-sm font-medium
           <?php echo e(request()->routeIs('login-logs.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'); ?>">
            Login Logs
        </a>
    </nav>

    <div class="mt-auto p-4 border-t">
        <form method="POST" action="<?php echo e(route('logout')); ?>">
            <?php echo csrf_field(); ?>
            <button class="w-full text-sm px-3 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-800">
                Logout
            </button>
        </form>
    </div>
</aside> -->


<template>
  <div class="flex">
    <Sidebar />
    <main class="flex-1">
      <router-view />
    </main>
  </div>
</template>

<script setup>
    import Sidebar from '@/components/Sidebar.vue'
</script>
<?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views\layouts\sidebar.blade.php ENDPATH**/ ?>