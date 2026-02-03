<script setup>
/** List skeleton for roles/permissions lists. */
defineProps({
  items: { type: Number, default: 5 },
})
</script>

<template>
  <div class="space-y-2">
    <div
      v-for="i in items"
      :key="i"
      class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3"
    >
      <div class="h-5 w-32 animate-pulse rounded bg-gray-200" />
      <div class="h-9 w-28 animate-pulse rounded bg-gray-200" />
    </div>
  </div>
</template>
