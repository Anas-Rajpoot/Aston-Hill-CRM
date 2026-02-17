<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import SidebarLink from './SidebarLink.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const logout = async () => {
  await auth.logout()
  router.push('/login')
}

const userHasRole = (role) => auth.user?.roles?.includes(role) ?? false

const primaryRole = computed(() => {
  const roles = auth.user?.roles ?? []
  if (roles.includes('superadmin')) return 'SA'
  if (roles.length) return String(roles[0]).slice(0, 2).toUpperCase()
  return 'U'
})

const navItems = [
  { to: '/', label: 'Dashboard' },
  { to: '/submissions', label: 'Submissions' },
  { to: '/lead-submissions', label: 'Lead Submissions' },
  { to: '/field-submissions', label: 'Field Submissions' },
  { to: '/customer-support', label: 'Customer Support' },
  { to: '/vas-requests', label: 'VAS Requests' },
  { to: '/clients', label: 'Clients' },
  { to: '/order-status', label: 'Order Status' },
  { to: '/dsp-tracker', label: 'DSP Tracker' },
  { to: '/verifiers-detail', label: 'Verifiers Detail' },
  { to: '/employees', label: 'Employees' },
  { to: '/cisco-extensions', label: 'Cisco Extensions' },
  { to: '/attendance-log', label: 'Attendance Log' },
  { to: '/expenses', label: 'Expense Tracker' },
  { to: '/personal-notes', label: 'Personal Notes' },
  { to: '/email-followups', label: 'Email Follow Up' },
  { to: '/reports', label: 'Reports' },
  { to: '/teams', label: 'Teams' },
]

const superAdminOnly = [
  { to: '/users', label: 'Users', show: () => userHasRole('superadmin') },
  { to: '/roles', label: 'Roles', show: () => userHasRole('superadmin') },
  { to: '/permissions', label: 'Permissions', show: () => userHasRole('superadmin') },
]

const isActive = (to) => {
  if (to === '/') return route.path === '/' || route.path === '/dashboard'
  return route.path.startsWith(to)
}
</script>

<template>
  <aside class="w-64 h-screen flex flex-col flex-shrink-0 overflow-hidden bg-gray-900 text-gray-200">
    <!-- Logo / Title -->
    <div class="h-16 flex items-center px-4 border-b border-gray-700">
      <span class="text-lg font-semibold text-white truncate">Aston Hill</span>
    </div>

    <!-- Main nav: custom scrollbar for dark theme -->
    <nav class="sidebar-nav flex-1 min-h-0 p-3 space-y-0.5 overflow-y-auto overflow-x-hidden">
      <SidebarLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        :label="item.label"
        dark
        :active="isActive(item.to)"
      />
      <SidebarLink
        v-for="item in superAdminOnly"
        :key="item.to"
        v-show="item.show?.()"
        :to="item.to"
        :label="item.label"
        dark
        :active="isActive(item.to)"
      />

      <!-- Settings -->
      <div class="pt-2">
        <SidebarLink
          to="/settings"
          label="Settings"
          dark
          :active="isActive('/settings')"
        />
      </div>
    </nav>

    <!-- Bottom user block -->
    <div class="p-3 border-t border-gray-700">
      <div class="flex items-center gap-3 px-2 py-2 rounded-md bg-gray-800/50">
        <div class="flex-shrink-0 w-9 h-9 rounded-full bg-gray-700 flex items-center justify-center text-xs font-medium text-white">
          {{ primaryRole }}
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium text-white truncate">{{ auth.user?.name ?? 'User' }}</p>
          <p class="text-xs text-gray-400 truncate">{{ auth.user?.email ?? '' }}</p>
        </div>
        <button
          type="button"
          class="flex-shrink-0 p-1.5 rounded text-gray-400 hover:text-white hover:bg-gray-700"
          title="Logout"
          @click="logout"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
        </button>
      </div>
    </div>
  </aside>
</template>

<style scoped>
/* Custom scrollbar for left menu – dark theme, thin and professional */
.sidebar-nav {
  scrollbar-width: thin;
  scrollbar-color: rgb(75 85 99) rgb(31 41 55);
}

.sidebar-nav::-webkit-scrollbar {
  width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
  background: rgb(31 41 55);
  border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb {
  background: rgb(75 85 99);
  border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
  background: rgb(107 114 128);
}

.sidebar-nav::-webkit-scrollbar-thumb:active {
  background: rgb(156 163 175);
}
</style>
