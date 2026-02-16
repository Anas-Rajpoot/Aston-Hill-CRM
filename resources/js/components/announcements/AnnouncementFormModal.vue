<script setup>
/**
 * Announcement Create / Edit modal.
 * Matches the design: Title, Type (radio), Content/Image, Priority,
 * Visibility (checkbox grid), Publish Options, Expiry, Acknowledgement.
 */
import { ref, reactive, watch, computed } from 'vue'
import api from '@/lib/axios'
import Toast from '@/components/Toast.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  mode: { type: String, default: 'create' },         // 'create' | 'edit'
  announcementId: { type: [Number, null], default: null },
})
const emit = defineEmits(['close', 'saved'])

const loading  = ref(false)
const saving   = ref(false)
const errors   = reactive({})
const showToast = ref(false)
const toastType = ref('error')
const toastMsg  = ref('')

/* ── Visibility role options ── */
const visibilityRoles = [
  { key: 'all_users', label: 'All Users' },
  { key: 'superadmin', label: 'Super Admin' },
  { key: 'back_office', label: 'Back Office' },
  { key: 'field_agent', label: 'Field Agent' },
  { key: 'sales', label: 'Sales' },
  { key: 'customer_support', label: 'Customer Support' },
]

const form = reactive({
  title: '',
  type: 'text',
  body: '',
  link_url: '',
  link_label: '',
  priority: 'high',
  all_users: true,
  audiences: { roles: [] },
  channels: ['web'],
  is_pinned: false,
  require_ack: false,
  ack_due_at: '',
  published_at: '',
  expire_at: '',
  publish_option: 'immediately',  // 'immediately' | 'schedule'
})

/* ── Visibility checkbox helpers ── */
const selectedRoles = reactive(new Set(['all_users']))

function toggleVisibility(key) {
  if (key === 'all_users') {
    // If checking "All Users", uncheck all others
    if (!selectedRoles.has('all_users')) {
      selectedRoles.clear()
      selectedRoles.add('all_users')
      form.all_users = true
      form.audiences = { roles: [] }
    }
  } else {
    // If checking a specific role, uncheck "All Users"
    if (selectedRoles.has(key)) {
      selectedRoles.delete(key)
    } else {
      selectedRoles.add(key)
      selectedRoles.delete('all_users')
    }
    // If nothing is selected, default back to all_users
    if (selectedRoles.size === 0) {
      selectedRoles.add('all_users')
      form.all_users = true
      form.audiences = { roles: [] }
    } else {
      form.all_users = selectedRoles.has('all_users')
      form.audiences = {
        roles: [...selectedRoles].filter(k => k !== 'all_users')
      }
    }
  }
}

function resetForm() {
  Object.assign(form, {
    title: '', type: 'text', body: '', link_url: '', link_label: '',
    priority: 'high', all_users: true, audiences: { roles: [] }, channels: ['web'],
    is_pinned: false, require_ack: false, ack_due_at: '', published_at: '', expire_at: '',
    publish_option: 'immediately',
  })
  selectedRoles.clear()
  selectedRoles.add('all_users')
  Object.keys(errors).forEach(k => delete errors[k])
}

function toLocalInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const off = d.getTimezoneOffset()
  const local = new Date(d.getTime() - off * 60000)
  return local.toISOString().slice(0, 16)
}

function toLocalDate(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const off = d.getTimezoneOffset()
  const local = new Date(d.getTime() - off * 60000)
  return local.toISOString().slice(0, 10)
}

watch(() => props.show, async (val) => {
  if (!val) return
  resetForm()
  if (props.mode === 'edit' && props.announcementId) {
    loading.value = true
    try {
      const { data } = await api.get(`/announcements/${props.announcementId}`)
      const a = data.data
      const isScheduled = a.published_at && new Date(a.published_at) > new Date()
      Object.assign(form, {
        title: a.title, type: a.type, body: a.body ?? '',
        link_url: a.link_url ?? '', link_label: a.link_label ?? '',
        priority: a.priority, all_users: a.all_users,
        audiences: a.audiences ?? { roles: [] }, channels: a.channels ?? ['web'],
        is_pinned: a.is_pinned, require_ack: a.require_ack,
        ack_due_at: toLocalInput(a.ack_due_at),
        published_at: toLocalInput(a.published_at),
        expire_at: toLocalDate(a.expire_at),
        publish_option: isScheduled ? 'schedule' : 'immediately',
      })
      // Restore visibility checkboxes
      selectedRoles.clear()
      if (a.all_users) {
        selectedRoles.add('all_users')
      } else if (a.audiences?.roles?.length) {
        a.audiences.roles.forEach(r => selectedRoles.add(r))
      } else {
        selectedRoles.add('all_users')
      }
    } catch { /* ignore */ }
    finally { loading.value = false }
  } else {
    form.published_at = toLocalInput(new Date().toISOString())
  }
})

async function submit() {
  if (saving.value) return
  saving.value = true
  Object.keys(errors).forEach(k => delete errors[k])

  const payload = { ...form }

  // Set publish_at based on option
  if (payload.publish_option === 'immediately') {
    payload.published_at = new Date().toISOString()
  }
  delete payload.publish_option

  // Normalize expire_at (date only → end of day)
  if (payload.expire_at && payload.expire_at.length === 10) {
    payload.expire_at = payload.expire_at + 'T23:59:59'
  }
  if (!payload.expire_at) delete payload.expire_at
  if (!payload.ack_due_at) delete payload.ack_due_at

  try {
    let res
    if (props.mode === 'edit' && props.announcementId) {
      res = await api.put(`/announcements/${props.announcementId}`, payload)
    } else {
      res = await api.post('/announcements', payload)
    }
    emit('saved', res.data?.data)
  } catch (e) {
    if (e?.response?.status === 422) {
      const fe = e.response.data?.errors ?? {}
      Object.keys(fe).forEach(k => { errors[k] = Array.isArray(fe[k]) ? fe[k].join(' ') : fe[k] })
    } else {
      toastType.value = 'error'; toastMsg.value = e?.response?.data?.message || 'Failed to save.'; showToast.value = true
    }
  } finally { saving.value = false }
}

const isEdit = computed(() => props.mode === 'edit')
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="emit('close')">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[92vh] flex flex-col overflow-hidden" @click.stop>
        <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />

        <!-- ── Header ── -->
        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
          <h3 class="text-lg font-bold text-gray-900">{{ isEdit ? 'Edit Announcement' : 'Create Announcement' }}</h3>
          <button class="p-1 rounded hover:bg-gray-100 text-gray-400" @click="emit('close')">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <!-- ── Body ── -->
        <div v-if="loading" class="flex-1 flex items-center justify-center py-16">
          <div class="h-8 w-8 animate-spin rounded-full border-4 border-teal-600 border-t-transparent" />
        </div>

        <div v-else class="flex-1 overflow-y-auto px-6 py-5 space-y-0">

          <!-- Title -->
          <div class="pb-5">
            <label class="block text-sm font-semibold text-gray-800 mb-1.5">Title <span class="text-red-500">*</span></label>
            <input
              v-model="form.title"
              type="text"
              class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition"
              placeholder="Enter announcement title"
            />
            <p v-if="errors.title" class="mt-1 text-xs text-red-600">{{ errors.title }}</p>
          </div>

          <div class="border-t border-gray-100" />

          <!-- Announcement Type -->
          <div class="py-5">
            <label class="block text-sm font-semibold text-gray-800 mb-2">Announcement Type <span class="text-red-500">*</span></label>
            <div class="flex items-center gap-6">
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" v-model="form.type" value="text" class="w-4 h-4 text-teal-600 focus:ring-teal-500 border-gray-300" />
                Text
              </label>
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" v-model="form.type" value="image" class="w-4 h-4 text-teal-600 focus:ring-teal-500 border-gray-300" />
                Image
              </label>
            </div>
            <p v-if="errors.type" class="mt-1 text-xs text-red-600">{{ errors.type }}</p>
          </div>

          <!-- Content (text type) -->
          <div v-if="form.type === 'text'" class="pb-5">
            <label class="block text-sm font-semibold text-gray-800 mb-1.5">Content <span class="text-red-500">*</span></label>
            <textarea
              v-model="form.body"
              rows="5"
              class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition resize-none"
              placeholder="Enter announcement content"
            />
            <p v-if="errors.body" class="mt-1 text-xs text-red-600">{{ errors.body }}</p>
          </div>

          <!-- Image URL (image type) -->
          <div v-if="form.type === 'image'" class="pb-5">
            <label class="block text-sm font-semibold text-gray-800 mb-1.5">Image URL <span class="text-red-500">*</span></label>
            <input
              v-model="form.link_url"
              type="url"
              class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition"
              placeholder="https://example.com/image.jpg"
            />
            <p v-if="errors.link_url" class="mt-1 text-xs text-red-600">{{ errors.link_url }}</p>
          </div>

          <div class="border-t border-gray-100" />

          <!-- Priority -->
          <div class="py-5">
            <label class="block text-sm font-semibold text-gray-800 mb-1.5">Priority <span class="text-red-500">*</span></label>
            <div class="relative">
              <select
                v-model="form.priority"
                class="w-full appearance-none rounded-lg border border-gray-300 px-3.5 py-2.5 pr-10 text-sm text-gray-900 bg-white focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition"
              >
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
              <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </div>
          </div>

          <div class="border-t border-gray-100" />

          <!-- Visibility -->
          <div class="py-5">
            <label class="block text-sm font-semibold text-gray-800 mb-2.5">Visibility <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-3 gap-x-4 gap-y-2.5">
              <label
                v-for="role in visibilityRoles"
                :key="role.key"
                class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer"
              >
                <input
                  type="checkbox"
                  :checked="selectedRoles.has(role.key)"
                  @change="toggleVisibility(role.key)"
                  class="w-4 h-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500"
                />
                {{ role.label }}
              </label>
            </div>
          </div>

          <div class="border-t border-gray-100" />

          <!-- Publish Options -->
          <div class="py-5">
            <label class="block text-sm font-semibold text-gray-800 mb-2">Publish Options</label>
            <div class="flex items-center gap-6">
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" v-model="form.publish_option" value="immediately" class="w-4 h-4 text-teal-600 focus:ring-teal-500 border-gray-300" />
                Publish Immediately
              </label>
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" v-model="form.publish_option" value="schedule" class="w-4 h-4 text-teal-600 focus:ring-teal-500 border-gray-300" />
                Schedule for Later
              </label>
            </div>
            <!-- Schedule date/time picker -->
            <div v-if="form.publish_option === 'schedule'" class="mt-3">
              <input
                v-model="form.published_at"
                type="datetime-local"
                class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition"
              />
              <p v-if="errors.published_at" class="mt-1 text-xs text-red-600">{{ errors.published_at }}</p>
            </div>
          </div>

          <div class="border-t border-gray-100" />

          <!-- Expiry Date -->
          <div class="py-5">
            <label class="block text-sm font-semibold text-gray-800 mb-1.5"><span class="italic">Expiry Date (Optional)</span></label>
            <input
              v-model="form.expire_at"
              type="date"
              class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition"
            />
            <p v-if="errors.expire_at" class="mt-1 text-xs text-red-600">{{ errors.expire_at }}</p>
          </div>

          <div class="border-t border-gray-100" />

          <!-- Require Acknowledgement -->
          <div class="py-5">
            <label class="flex items-start gap-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.require_ack"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500"
              />
              <div>
                <span class="text-sm font-semibold text-gray-800">Require Acknowledgement</span>
                <p class="text-xs text-gray-500 mt-0.5">Users must mark this announcement as read</p>
              </div>
            </label>
          </div>
        </div>

        <!-- ── Footer ── -->
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3 bg-white">
          <button
            type="button"
            class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
            @click="emit('close')"
          >Cancel</button>
          <button
            type="button"
            :disabled="saving"
            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
            @click="submit"
          >
            <svg v-if="saving" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ saving ? 'Saving…' : (isEdit ? 'Save Changes' : 'Create Announcement') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
