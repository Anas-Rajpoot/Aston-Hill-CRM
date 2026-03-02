<script setup>
/**
 * Progressive rendering: Tabs and card shell render immediately.
 * Tab content is lazy-loaded; each async component shows a skeleton while loading (no Suspense).
 */
import { ref, onMounted, defineAsyncComponent, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Tabs from '@/components/Tabs.vue'
import SubmissionFormSkeleton from '@/components/skeletons/SubmissionFormSkeleton.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

function asyncForm(loader) {
  return defineAsyncComponent({
    loader,
    loadingComponent: SubmissionFormSkeleton,
    delay: 100,
  })
}

const LeadSubmissionWizard = asyncForm(() => import('@/forms/LeadSubmissionForms/LeadSubmissionWizard.vue'))
const FieldSubmissionForm = asyncForm(() => import('@/forms/FieldSubmissionForm/step1.vue'))
const CustomerSupportForm = asyncForm(() => import('@/forms/CustomerSupportForm/step1.vue'))
const VasRequestWizard = asyncForm(() => import('@/forms/VASRequestForm/VasRequestWizard.vue'))
const NewSubmissionForm = asyncForm(() => import('@/forms/NewSubmissionForm/NewSubmissionForm.vue'))

const route = useRoute()
const auth = useAuthStore()
const activeTab = ref('lead')
/** Incremented on every tab click so the visible form remounts (clears success message, shows fresh form). */
const formKey = ref(0)
/** When true, lead wizard mounts in "new submission" mode and does not load current draft. */
const leadForceNewForm = ref(false)

const allTabs = [
  { key: 'lead', label: 'Lead Submissions' },
  { key: 'field', label: 'Field Submissions' },
  { key: 'support', label: 'Customer Support' },
  { key: 'vas', label: 'VAS Requests' },
  { key: 'new', label: 'Special Request' },
]

const isSuperAdmin = computed(() => auth.user?.roles?.includes('superadmin') ?? false)
const userPermissions = computed(() => auth.user?.permissions ?? [])

function hasAnyPermission(keys = []) {
  if (isSuperAdmin.value) return true
  const perms = new Set((userPermissions.value ?? []).map((p) => String(p)))
  return keys.some((k) => perms.has(k))
}

const createPermissionMap = {
  lead: ['lead-submissions.create', 'lead.create'],
  field: ['field-submissions.create', 'field.create'],
  support: ['customer_support_requests.create', 'customer-support-requests.create', 'customer_support.create', 'customer-support.create'],
  vas: ['vas_requests.create', 'vas-requests.create', 'vas.create'],
  new: ['special_requests.create', 'special-requests.create', 'special.create'],
}

const tabs = computed(() => allTabs.filter((tab) => hasAnyPermission(createPermissionMap[tab.key] || [])))

const hiddenTabsLabels = computed(() => allTabs
  .filter((tab) => !tabs.value.some((allowed) => allowed.key === tab.key))
  .map((tab) => tab.label))

function ensureActiveTabAvailable() {
  if (!tabs.value.length) {
    activeTab.value = ''
    return
  }
  if (!tabs.value.some((tab) => tab.key === activeTab.value)) {
    activeTab.value = tabs.value[0].key
    formKey.value = Date.now()
  }
}

onMounted(() => {
  if (route.query.vas_request_id != null && route.query.vas_request_id !== '') {
    activeTab.value = 'vas'
  }
  ensureActiveTabAvailable()
})

watch(tabs, ensureActiveTabAvailable, { deep: true })

function onTabChange(key) {
  activeTab.value = key
  formKey.value = Date.now()
}

function onLeadNewSubmission() {
  leadForceNewForm.value = true
  formKey.value = Date.now()
  window.setTimeout(() => { leadForceNewForm.value = false }, 500)
}
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6">
    <div class="w-full space-y-6">
      <div class="flex flex-wrap items-baseline gap-2">
        <h1 class="text-xl font-semibold text-gray-900 leading-tight ml-4">Submissions</h1>
        <Breadcrumbs />
      </div>
      <!-- TABS (dark bar like 1st image) -->
      <Tabs :tabs="tabs" :active="activeTab" @change="onTabChange" />
      <div v-if="hiddenTabsLabels.length" class="mx-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800">
        You do not have permission for: {{ hiddenTabsLabels.join(', ') }}.
      </div>

      <!-- TAB CONTENT: one async component per tab; each shows skeleton while loading. -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-6 pb-6 pt-3 sm:px-8 sm:pb-8 sm:pt-4 !mt-0">
        <LeadSubmissionWizard
          v-if="activeTab === 'lead'"
          :key="`lead-${formKey}`"
          :force-new-form="leadForceNewForm"
          @new-submission="onLeadNewSubmission"
        />
        <FieldSubmissionForm v-else-if="activeTab === 'field'" :key="`field-${formKey}`" />
        <CustomerSupportForm v-else-if="activeTab === 'support'" :key="`support-${formKey}`" />
        <VasRequestWizard v-else-if="activeTab === 'vas'" :key="`vas-${formKey}`" />
        <NewSubmissionForm v-else-if="activeTab === 'new'" :key="`new-${formKey}`" />
        <div v-else class="py-10 text-center text-sm text-gray-500">
          You do not have permission to add submission forms.
        </div>
      </div>
    </div>
  </div>
</template>
