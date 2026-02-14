<script setup>
/**
 * Email Template modal — supports both Create and Edit modes.
 *
 * Props:
 *  - show: boolean — whether the modal is visible
 *  - templateId: number|null — if set, loads and edits an existing template; if null, creates a new one
 *  - canUpdate: boolean — whether the user has permission to save changes
 *  - triggers: array — available trigger keys for the dropdown (used in create mode)
 */
import { ref, watch, computed } from 'vue'

function wrapVar(v) { return '\u007B\u007B' + v + '\u007D\u007D' }
import api from '@/lib/axios'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  templateId: { type: [Number, null], default: null },
  canUpdate: { type: Boolean, default: false },
  triggers: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'saved', 'created'])

const loading    = ref(false)
const saving     = ref(false)
const template   = ref(null)
const errors     = ref({})

// Form fields
const triggerKey = ref('')
const name       = ref('')
const subject    = ref('')
const body       = ref('')

const isCreateMode = computed(() => !props.templateId)
const modalTitle   = computed(() => isCreateMode.value ? 'Add Email Template' : 'Edit Email Template')

// Default available variables
const defaultVars = ['CompanyName', 'SubmissionRef', 'CreatedAt', 'AssignedTo', 'Status']

watch(() => props.show, async (val) => {
  if (!val) return

  errors.value = {}

  if (props.templateId) {
    // Edit mode: load existing template
    loading.value = true
    try {
      const { data } = await api.get(`/email-templates/${props.templateId}`)
      template.value = data.data
      triggerKey.value = data.data.trigger_key || ''
      name.value     = data.data.name || ''
      subject.value  = data.data.subject || ''
      body.value     = data.data.body || ''
    } catch { template.value = null }
    finally { loading.value = false }
  } else {
    // Create mode: reset form
    template.value  = null
    triggerKey.value = ''
    name.value      = ''
    subject.value   = ''
    body.value      = ''
  }
})

async function save() {
  if (!props.canUpdate || saving.value) return
  saving.value = true
  errors.value = {}

  try {
    if (isCreateMode.value) {
      // Create new template
      const { data } = await api.post('/email-templates', {
        trigger_key: triggerKey.value,
        name: name.value,
        subject: subject.value,
        body: body.value,
      })
      emit('created', data.data || data)
    } else {
      // Update existing template
      const { data } = await api.put(`/email-templates/${props.templateId}`, {
        name: name.value,
        subject: subject.value,
        body: body.value,
      })
      emit('saved', data.data || data)
    }
  } catch (e) {
    if (e?.response?.status === 422) {
      errors.value = e.response.data?.errors ?? {}
    }
  } finally { saving.value = false }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="emit('close')">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden" @click.stop>
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">{{ modalTitle }}</h3>
          <button class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-600" @click="emit('close')">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
          <template v-if="loading">
            <SkeletonBox width="40%" height="16px" />
            <SkeletonBox width="100%" height="38px" class="mt-4" />
            <SkeletonBox width="100%" height="38px" class="mt-4" />
            <SkeletonBox width="100%" height="140px" class="mt-4" />
          </template>
          <template v-else>
            <!-- Trigger Event -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Trigger Event</label>
              <template v-if="isCreateMode">
                <select
                  v-model="triggerKey"
                  :disabled="!canUpdate"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:opacity-70"
                >
                  <option value="" disabled>Select trigger event…</option>
                  <option v-for="t in triggers" :key="t.key" :value="t.key">{{ t.name }}</option>
                </select>
                <p v-if="errors.trigger_key" class="mt-1 text-xs text-red-600">{{ Array.isArray(errors.trigger_key) ? errors.trigger_key.join(' ') : errors.trigger_key }}</p>
              </template>
              <template v-else>
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-600">{{ (template?.trigger_key ?? triggerKey)?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }}</div>
              </template>
            </div>

            <!-- Template Name -->
            <div>
              <label for="tpl_name" class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
              <input
                id="tpl_name"
                v-model="name"
                type="text"
                :disabled="!canUpdate"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:opacity-70"
                placeholder="e.g. New Submission Notification"
              />
              <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ Array.isArray(errors.name) ? errors.name.join(' ') : errors.name }}</p>
            </div>

            <!-- Subject -->
            <div>
              <label for="tpl_subject" class="block text-sm font-medium text-gray-700 mb-1">Email Subject</label>
              <input
                id="tpl_subject"
                v-model="subject"
                type="text"
                :disabled="!canUpdate"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:opacity-70"
                placeholder="New Submission - {{CompanyName}}"
              />
              <p v-if="errors.subject" class="mt-1 text-xs text-red-600">{{ Array.isArray(errors.subject) ? errors.subject.join(' ') : errors.subject }}</p>
            </div>

            <!-- Body -->
            <div>
              <label for="tpl_body" class="block text-sm font-medium text-gray-700 mb-1">Email Body Template</label>
              <textarea
                id="tpl_body"
                v-model="body"
                rows="8"
                :disabled="!canUpdate"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono leading-relaxed focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:opacity-70"
              />
              <p v-if="errors.body" class="mt-1 text-xs text-red-600">{{ Array.isArray(errors.body) ? errors.body.join(' ') : errors.body }}</p>
            </div>

            <!-- Available variables -->
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
              <p class="text-xs font-medium text-gray-600 mb-1">Available Variables</p>
              <div class="flex flex-wrap gap-1.5">
                <code
                  v-for="v in (template?.available_variables ?? defaultVars)" :key="v"
                  class="rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-800 font-mono"
                >{{ wrapVar(v) }}</code>
              </div>
            </div>

            <!-- Info -->
            <div class="flex items-start gap-2 text-xs text-blue-700 bg-blue-50 rounded-lg px-3 py-2">
              <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
              <span>Variables in double braces are automatically replaced with actual values when notifications are sent.</span>
            </div>
          </template>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3 bg-gray-50">
          <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="emit('close')">Close</button>
          <button
            v-if="canUpdate"
            type="button"
            :disabled="saving || loading"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
            @click="save"
          >
            <svg v-if="saving" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
            {{ saving ? 'Saving…' : (isCreateMode ? 'Create Template' : 'Save Changes') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
