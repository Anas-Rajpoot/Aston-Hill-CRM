<script setup>
/** Section-level skeleton for user edit page (e.g. Basic Information, Role & Hierarchy). */
defineProps({
  lines: { type: Number, default: 6 },
})
</script>

<template>
  <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
      <div class="h-5 w-5 rounded bg-gray-200 animate-pulse" />
      <div class="h-4 w-24 rounded bg-gray-200 animate-pulse" />
    </div>
    <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="i in lines" :key="i" class="space-y-1">
        <div class="h-4 w-24 rounded bg-gray-200 animate-pulse" />
        <div class="h-10 w-full rounded-lg bg-gray-100 animate-pulse" />
      </div>
    </div>
  </div>
</template>
