<script setup>
import Breadcrumbs from '@/components/Breadcrumbs.vue'
defineProps({ title: { type: String, default: 'Page' } })
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-semibold text-gray-800">{{ title }}</h1>
    <Breadcrumbs />
    <p class="mt-2 text-gray-500">This page is coming soon.</p>
  </div>
</template>
