<script setup>
/**
 * Progressive rendering: Tabs and card shell render immediately.
 * Tab content is lazy-loaded; each async component shows a skeleton while loading (no Suspense).
 */
import { ref, onMounted, defineAsyncComponent } from 'vue'
import { useRoute } from 'vue-router'
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
const activeTab = ref('lead')
/** Incremented on every tab click so the visible form remounts (clears success message, shows fresh form). */
const formKey = ref(0)
/** When true, lead wizard mounts in "new submission" mode and does not load current draft. */
const leadForceNewForm = ref(false)

const tabs = [
  { key: 'lead', label: 'Lead Submissions' },
  { key: 'field', label: 'Field Submissions' },
  { key: 'support', label: 'Customer Support' },
  { key: 'vas', label: 'VAS Requests' },
  { key: 'new', label: 'Special Request' },
]

onMounted(() => {
  if (route.query.vas_request_id != null && route.query.vas_request_id !== '') {
    activeTab.value = 'vas'
  }
})

function onTabChange(key) {
  activeTab.value = key
  formKey.value = Date.now()
}

function onLeadNewSubmission() {
  leadForceNewForm.value = true
  formKey.value = Date.now()
  setTimeout(() => { leadForceNewForm.value = false }, 500)
}
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] py-6">
    <div class="w-full space-y-6">
      <div class="flex flex-wrap items-baseline gap-2">
        <h1 class="text-xl font-semibold text-gray-900 leading-tight">Submissions</h1>
        <Breadcrumbs />
      </div>
      <!-- TABS (dark bar like 1st image) -->
      <Tabs
        :tabs="tabs"
        :active="activeTab"
        @change="onTabChange"
      />

      <!-- TAB CONTENT: one async component per tab; each shows skeleton while loading. -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sm:p-8">
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
      </div>
    </div>
  </div>
</template>
