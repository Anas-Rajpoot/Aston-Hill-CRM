<script setup>
/**
 * Notifications & Announcements — system alerts, announcements, notification history.
 */
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/lib/axios'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'
import { canModuleAction } from '@/lib/accessControl'

const auth = useAuthStore()
const canRefresh = computed(() => canModuleAction(auth.user, 'notifications', 'view', ['notifications.view', 'notifications.list']))
const canReadAll = computed(() => canModuleAction(auth.user, 'notifications', 'edit', ['notifications.edit', 'notifications.update']))
const canMarkRead = computed(() => canModuleAction(auth.user, 'notifications', 'edit', ['notifications.edit', 'notifications.update']))
const loading = ref(true)
const notifications = ref([])
const announcements = ref([])
const activeTab = ref('notifications')
const notificationMeta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
})
const perPageOptions = [10, 20, 25, 50]
const notificationStatusFilter = ref('all')
const notificationCounts = ref({
  all: 0,
  unread: 0,
  read: 0,
})
const notificationFilterOptions = [
  { value: 'all', label: 'All' },
  { value: 'unread', label: 'Unread' },
  { value: 'read', label: 'Read' },
]

function syncTopbarBadge() {
  if (typeof window === 'undefined') return
  const unread = Number(notificationCounts.value.unread || 0)
  window.dispatchEvent(new CustomEvent('notifications:badge-sync', {
    detail: {
      unreadCount: unread,
      badge: unread > 5 ? '5+' : String(unread),
    },
  }))
}

async function loadNotificationCounts() {
  const perPage = 1
  const [allRes, unreadRes] = await Promise.all([
    api.get('/notifications', { params: { page: 1, per_page: perPage } }),
    api.get('/notifications', { params: { page: 1, per_page: perPage, status: 'unread' } }),
  ])
  const allTotal = Number(allRes?.data?.total ?? 0) || 0
  const unreadTotal = Number(unreadRes?.data?.total ?? 0) || 0
  notificationCounts.value = {
    all: allTotal,
    unread: unreadTotal,
    read: Math.max(0, allTotal - unreadTotal),
  }
  syncTopbarBadge()
}

async function loadNotifications(page = notificationMeta.value.current_page) {
  const targetPage = Math.max(1, Number(page) || 1)
  const perPage = Math.max(1, Number(notificationMeta.value.per_page) || 10)
  const status = notificationStatusFilter.value
  const params = { page: targetPage, per_page: perPage }
  if (status !== 'all') params.status = status
  const { data } = await api.get('/notifications', {
    params,
  })
  notifications.value = Array.isArray(data?.data) ? data.data : []
  notificationMeta.value = {
    current_page: Number(data?.current_page ?? targetPage) || 1,
    last_page: Number(data?.last_page ?? 1) || 1,
    per_page: Number(data?.per_page ?? perPage) || perPage,
    total: Number(data?.total ?? notifications.value.length) || 0,
  }
  await loadNotificationCounts()
}

async function loadAnnouncements() {
  const { data } = await api.get('/announcements', {
    params: {
      status: 'active',
      sort: 'published_at:desc',
      per_page: 20,
      include_system_generated: 1,
    },
  })
  const rows = data?.data ?? data ?? []
  announcements.value = Array.isArray(rows)
    ? rows.map(a => ({
        ...a,
        content: a.content || a.body || a.message || '',
      }))
    : []
}

async function load() {
  loading.value = true
  try {
    await Promise.all([loadNotifications(), loadAnnouncements()])
  } catch { /* silent */ }
  finally { loading.value = false }
}

onMounted(load)

async function markNotificationRead(id) {
  try {
    await api.post(`/notifications/${id}/read`)
    if (notificationStatusFilter.value === 'unread') {
      await loadNotifications(notificationMeta.value.current_page)
      return
    }
    const row = notifications.value.find((n) => n.id === id)
    if (row) row.is_unread = false
  } catch { /* silent */ }
}

async function markNotificationUnread(id) {
  try {
    await api.post(`/notifications/${id}/unread`)
    if (notificationStatusFilter.value === 'read') {
      await loadNotifications(notificationMeta.value.current_page)
      return
    }
    const row = notifications.value.find((n) => n.id === id)
    if (row) row.is_unread = true
  } catch { /* silent */ }
}

const unreadCount = computed(() => notifications.value.filter((n) => n.is_unread).length)

async function markAllRead() {
  if (!notifications.value.length || unreadCount.value === 0 || loading.value) return
  loading.value = true
  try {
    await api.post('/notifications/read-all')
    await loadNotifications(notificationMeta.value.current_page)
  } finally {
    loading.value = false
  }
}

async function onPageChange(page) {
  if (page < 1 || page > notificationMeta.value.last_page || loading.value) return
  loading.value = true
  try {
    await loadNotifications(page)
  } finally {
    loading.value = false
  }
}

async function onPerPageChange(event) {
  const nextPerPage = Math.max(1, Number(event?.target?.value) || 10)
  notificationMeta.value.per_page = nextPerPage
  loading.value = true
  try {
    await loadNotifications(1)
  } finally {
    loading.value = false
  }
}

async function onNotificationStatusFilterChange(status) {
  if (status === notificationStatusFilter.value || loading.value) return
  notificationStatusFilter.value = status
  loading.value = true
  try {
    await loadNotifications(1)
  } finally {
    loading.value = false
  }
}

function timeAgo(iso) {
  if (!iso) return '—'
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'Just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  return `${Math.floor(hrs / 24)}d ago`
}

function channelIcon(channel) {
  if (channel === 'email') return 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'
  if (channel === 'database') return 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4'
  return 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'
}
</script>

<template>
  <div class="space-y-5 bg-white">
    <!-- Tabs + Actions -->
    <div class="flex flex-col gap-3 border-b border-gray-200 pb-2 sm:flex-row sm:items-end sm:justify-between">
      <div class="min-w-0 flex-1 overflow-x-auto [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
        <div class="flex w-max min-w-full gap-1">
          <button
            v-for="tab in ['notifications', 'announcements']"
            :key="tab"
            @click="activeTab = tab"
            class="px-3 sm:px-4 py-2.5 text-xs sm:text-sm font-medium border-b-2 transition capitalize whitespace-nowrap"
            :class="activeTab === tab ? 'border-brand-primary text-brand-primary-hover' : 'border-transparent text-gray-500 hover:text-gray-700'"
          >{{ tab }}</button>
        </div>
      </div>
      <div class="mb-1 flex shrink-0 flex-wrap items-center gap-2">
        <button
          v-if="activeTab === 'notifications'"
          v-show="canReadAll"
          :disabled="loading || unreadCount === 0"
          @click="markAllRead"
          class="inline-flex items-center gap-2 rounded-lg border border-brand-primary bg-white px-3 py-2 text-sm font-medium text-brand-primary transition hover:bg-brand-primary-light disabled:cursor-not-allowed disabled:opacity-50"
        >
          Read All
        </button>
        <button v-if="canRefresh" @click="load" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-primary-hover">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          Refresh
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="bg-white rounded-lg border p-4">
        <SkeletonBox class="h-4 w-48 mb-2" /><SkeletonBox class="h-3 w-full" />
      </div>
    </div>

    <!-- Notifications -->
    <div v-else-if="activeTab === 'notifications'">
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <button
          v-for="opt in notificationFilterOptions"
          :key="opt.value"
          type="button"
          class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
          :class="notificationStatusFilter === opt.value ? 'border-brand-primary bg-brand-primary text-white shadow-sm' : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100'"
          @click="onNotificationStatusFilterChange(opt.value)"
        >
          {{ opt.label }} ({{ notificationCounts[opt.value] ?? 0 }})
        </button>
      </div>
      <div v-if="!notifications.length" class="rounded-xl border bg-white p-8 text-center sm:p-12">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
        <p class="text-gray-500 text-sm">No notifications yet</p>
      </div>
      <ul v-else class="grid grid-cols-1 gap-3 lg:grid-cols-2">
        <li
          v-for="n in notifications"
          :key="n.id"
          class="flex h-full flex-col gap-3 rounded-lg border p-4 transition sm:flex-row sm:items-start"
          :class="n.is_unread ? 'border-brand-primary/40 bg-brand-primary-light/30 hover:border-brand-primary' : 'border-gray-200 bg-white hover:border-brand-primary-muted'"
        >
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
            :class="n.is_unread ? 'bg-brand-primary-light' : 'bg-gray-100'"
          >
            <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="channelIcon(n.channel)" />
            </svg>
          </div>
          <div class="min-w-0 flex-1">
            <div class="mb-1 flex items-center gap-2">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                :class="n.is_unread ? 'bg-brand-primary text-white' : 'bg-gray-100 text-gray-600'"
              >
                {{ n.is_unread ? 'Unread' : 'Read' }}
              </span>
            </div>
            <p class="text-sm font-medium text-gray-900">{{ n.subject || n.title || n.type || 'Notification' }}</p>
            <p class="mt-0.5 line-clamp-3 text-xs text-gray-500">{{ n.message || n.body || '' }}</p>
            <p class="text-[10px] text-gray-400 mt-1">{{ timeAgo(n.created_at) }}</p>
          </div>
          <button
            v-if="n.is_unread"
            v-show="canMarkRead"
            @click="markNotificationRead(n.id)"
            class="self-start rounded-md border border-brand-primary/30 px-2 py-1 text-[11px] font-medium text-brand-primary transition hover:bg-brand-primary-light sm:self-center"
            title="Mark as read"
          >
            Mark Read
          </button>
          <button
            v-else
            v-show="canMarkRead"
            @click="markNotificationUnread(n.id)"
            class="self-start rounded-md border border-gray-300 px-2 py-1 text-[11px] font-medium text-gray-600 transition hover:bg-gray-50 sm:self-center"
            title="Mark as unread"
          >
            Mark Unread
          </button>
        </li>
      </ul>
      <div v-if="notificationMeta.total > 0" class="mt-3 flex flex-col gap-3 border-t border-gray-200 bg-white px-1 pt-3 lg:flex-row lg:items-center lg:justify-between">
        <p class="text-sm text-gray-600">
          Showing {{ notificationMeta.total ? ((notificationMeta.current_page - 1) * notificationMeta.per_page) + 1 : 0 }}
          to {{ Math.min(notificationMeta.current_page * notificationMeta.per_page, notificationMeta.total) }}
          of {{ notificationMeta.total }} entries
        </p>
        <div class="flex flex-wrap items-center gap-3 sm:gap-4">
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <span class="whitespace-nowrap font-medium">Number of rows</span>
            <select
              :value="notificationMeta.per_page"
              class="min-w-[80px] rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              @change="onPerPageChange"
            >
              <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>
          <div class="flex flex-wrap items-center gap-1.5">
            <button
              type="button"
              :disabled="notificationMeta.current_page <= 1 || loading"
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              @click="onPageChange(notificationMeta.current_page - 1)"
            >
              Previous
            </button>
            <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">
              Page {{ notificationMeta.current_page }} of {{ notificationMeta.last_page }}
            </span>
            <button
              type="button"
              :disabled="notificationMeta.current_page >= notificationMeta.last_page || loading"
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              @click="onPageChange(notificationMeta.current_page + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Announcements -->
    <div v-else>
      <div v-if="!announcements.length" class="bg-white rounded-xl border p-12 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
        <p class="text-gray-500 text-sm">No announcements</p>
      </div>
      <ul v-else class="space-y-3">
        <li v-for="a in announcements" :key="a.id" class="bg-white rounded-xl border border-gray-200 p-5 hover:border-brand-primary-muted transition">
          <div class="flex items-center gap-2 mb-2">
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-brand-primary-light text-brand-primary-hover capitalize">{{ a.type || 'announcement' }}</span>
            <span class="text-xs text-gray-400">{{ timeAgo(a.created_at) }}</span>
          </div>
          <h3 class="text-sm font-semibold text-gray-900">{{ a.title }}</h3>
          <p class="text-sm text-gray-600 mt-1">{{ a.content || a.body || a.message || '' }}</p>
        </li>
      </ul>
    </div>
  </div>
</template>
