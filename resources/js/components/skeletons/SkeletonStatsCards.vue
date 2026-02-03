<script setup>
/** Stats cards skeleton (e.g. Total / Active / Assigned). */
defineProps({
  count: { type: Number, default: 3 },
})
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div
      v-for="i in count"
      :key="i"
      class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
    >
      <div class="h-4 w-24 animate-pulse rounded bg-gray-200" />
      <div class="mt-2 h-8 w-12 animate-pulse rounded bg-gray-200" />
    </div>
  </div>
</template>
