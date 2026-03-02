<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSidebar } from '@/composables/useSidebar'
import SidebarLink from './SidebarLink.vue'
import { canAccessRoute, isSuperAdmin } from '@/lib/accessControl'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { collapsed, toggle } = useSidebar()

const logout = async () => {
  await auth.logout()
  router.push('/login')
}

const primaryRole = computed(() => {
  const roles = auth.user?.roles ?? []
  if (roles.includes('superadmin')) return 'SA'
  if (roles.length) return String(roles[0]).slice(0, 2).toUpperCase()
  return 'U'
})

const navItems = [
  { to: '/', label: 'Dashboard', abbr: 'Da' },
  { to: '/submissions', label: 'Submissions', abbr: 'Su' },
  { to: '/lead-submissions', label: 'Lead Submissions', abbr: 'LS' },
  { to: '/field-submissions', label: 'Field Submissions', abbr: 'FS' },
  { to: '/customer-support', label: 'Customer Support', abbr: 'CS' },
  { to: '/vas-requests', label: 'VAS Requests', abbr: 'VR' },
  { to: '/special-requests', label: 'Special Requests', abbr: 'SR' },
  { to: '/all-clients', label: 'All Clients', abbr: 'AC' },
  { to: '/clients', label: 'Clients', abbr: 'Cl' },
  { to: '/order-status', label: 'Order Status', abbr: 'OS' },
  { to: '/dsp-tracker', label: 'DSP Tracker', abbr: 'DT' },
  { to: '/verifiers-detail', label: 'Verifiers Detail', abbr: 'VD' },
  { to: '/cisco-extensions', label: 'Cisco Extensions', abbr: 'CE' },
  { to: '/attendance-log', label: 'Attendance Log', abbr: 'AL' },
  { to: '/expenses', label: 'Expense Tracker', abbr: 'ET' },
  { to: '/personal-notes', label: 'Personal Notes', abbr: 'PN' },
  { to: '/email-followups', label: 'Email Follow Up', abbr: 'EF' },
  { to: '/reports', label: 'Reports', abbr: 'Re' },
]

const visibleNavItems = computed(() => navItems.filter((item) => canAccessRoute(auth.user, item.to)))

const settingsChildren = computed(() => {
  const items = []
  if (isSuperAdmin(auth.user)) {
    items.push(
      { to: '/users', label: 'Users', abbr: 'Us' },
      { to: '/teams', label: 'Teams', abbr: 'Te' },
      { to: '/roles', label: 'Roles', abbr: 'Ro' },
      { to: '/permissions', label: 'Permissions', abbr: 'Pe' },
      { to: '/settings', label: 'Settings', abbr: 'Se' },
    )
  } else {
    if (canAccessRoute(auth.user, '/users')) items.push({ to: '/users', label: 'Users', abbr: 'Us' })
    if (canAccessRoute(auth.user, '/teams')) items.push({ to: '/teams', label: 'Teams', abbr: 'Te' })
  }
  return items
})

const settingsOpen = ref(false)

const settingsGroupActive = computed(() =>
  settingsChildren.value.some((c) => isActive(c.to))
)

function toggleSettings() {
  settingsOpen.value = !settingsOpen.value
}

const isActive = (to) => {
  if (to === '/') return route.path === '/' || route.path === '/dashboard'
  return route.path.startsWith(to)
}
</script>

<template>
  <aside
    class="h-screen flex flex-col flex-shrink-0 overflow-hidden bg-gray-900 text-gray-200 transition-all duration-300 ease-in-out"
    :class="collapsed ? 'w-[68px]' : 'w-64'"
  >
    <!-- Logo / Title + Toggle -->
    <div class="h-16 flex items-center border-b border-gray-700" :class="collapsed ? 'justify-center px-2' : 'justify-between px-4'">
      <span v-if="!collapsed" class="text-lg font-semibold text-white truncate">Aston Hill</span>
      <button
        type="button"
        class="p-1.5 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 transition-colors"
        :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        @click="toggle"
      >
        <svg v-if="collapsed" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
        </svg>
        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
        </svg>
      </button>
    </div>

    <!-- Main nav -->
    <nav class="sidebar-nav flex-1 min-h-0 p-2 space-y-0.5 overflow-y-auto overflow-x-hidden">
      <SidebarLink
        v-for="item in visibleNavItems"
        :key="item.to"
        :to="item.to"
        :label="item.label"
        :abbr="item.abbr"
        :collapsed="collapsed"
        dark
        :active="isActive(item.to)"
      />
      <!-- Settings group -->
      <div v-if="settingsChildren.length" class="pt-2">
        <button
          type="button"
          class="group relative w-full rounded-md text-sm font-medium transition-colors"
          :class="[
            collapsed ? 'flex items-center justify-center px-0 py-2' : 'flex items-center justify-between px-3 py-2',
            settingsGroupActive ? 'bg-lime-600/20 text-lime-400' : 'text-gray-300 hover:bg-gray-800 hover:text-white',
          ]"
          :title="collapsed ? 'Settings' : undefined"
          @click="toggleSettings"
        >
          <span v-if="collapsed" class="text-xs font-bold leading-none">Se</span>
          <span v-else>Settings</span>
          <svg
            v-if="!collapsed"
            class="w-4 h-4 transition-transform duration-200"
            :class="settingsOpen || settingsGroupActive ? 'rotate-180' : ''"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
          <span
            v-if="collapsed"
            class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded bg-gray-800 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
          >Settings</span>
        </button>

        <div v-show="!collapsed && (settingsOpen || settingsGroupActive)" class="ml-3 mt-0.5 space-y-0.5 border-l border-gray-700 pl-2">
          <SidebarLink
            v-for="child in settingsChildren"
            :key="child.to"
            :to="child.to"
            :label="child.label"
            :abbr="child.abbr"
            :collapsed="false"
            dark
            sub
            :active="isActive(child.to)"
          />
        </div>
      </div>
    </nav>

    <!-- Bottom user block -->
    <div class="border-t border-gray-700" :class="collapsed ? 'p-2' : 'p-3'">
      <div
        class="flex items-center rounded-md bg-gray-800/50"
        :class="collapsed ? 'justify-center px-1 py-2' : 'gap-3 px-2 py-2'"
      >
        <div class="flex-shrink-0 w-9 h-9 rounded-full bg-gray-700 flex items-center justify-center text-xs font-medium text-white">
          {{ primaryRole }}
        </div>
        <template v-if="!collapsed">
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
        </template>
      </div>
    </div>
  </aside>
</template>

<style scoped>
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
