<script setup>
import { ref } from 'vue'
import StatsCards from '@/components/StatsCards.vue'
import Tabs from '@/components/Tabs.vue'

// Forms
import LeadSubmissionForm from '@/forms/LeadSubmissionForm/step1.vue'
import FieldSubmissionForm from '@/forms/FieldSubmissionForm/step1.vue'
import CustomerSupportForm from '@/forms/CustomerSupportForm/step1.vue'
import VASRequestForm from '@/forms/VASRequestForm/step1.vue'

const activeTab = ref('lead')

const tabs = [
  { key: 'lead', label: 'Lead Submissions' },
  { key: 'field', label: 'Field Submissions' },
  { key: 'support', label: 'Customer Support' },
  { key: 'vas', label: 'VAS Requests' },
]
</script>

<template>
  <div class="space-y-6">

    <!-- STATS -->
    <StatsCards />

    <!-- TABS -->
    <Tabs
      :tabs="tabs"
      :active="activeTab"
      @change="activeTab = $event"
    />

    <!-- TAB CONTENT -->
    <div class="bg-white rounded-xl shadow p-6">
      <LeadSubmissionForm v-if="activeTab === 'lead'" />
      <FieldSubmissionForm v-if="activeTab === 'field'" />
      <CustomerSupportForm v-if="activeTab === 'support'" />
      <VASRequestForm v-if="activeTab === 'vas'" />
    </div>

  </div>
</template>
