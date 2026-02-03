<script setup>
/** Skeleton for permission module cards (header + grid of items). */
defineProps({
  modules: { type: Number, default: 3 },
  itemsPerModule: { type: Number, default: 6 },
})
</script>

<template>
  <div class="space-y-6">
    <div
      v-for="m in modules"
      :key="m"
      class="rounded-xl border border-gray-200 bg-white overflow-hidden"
    >
      <div class="flex items-center justify-between gap-4 px-4 py-3 bg-gray-50 border-b border-gray-200">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 animate-pulse rounded-lg bg-gray-200" />
          <div>
            <div class="h-5 w-40 animate-pulse rounded bg-gray-200" />
            <div class="mt-1 h-3 w-24 animate-pulse rounded bg-gray-200" />
          </div>
        </div>
        <div class="h-4 w-20 animate-pulse rounded bg-gray-200" />
      </div>
      <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
        <div
          v-for="i in itemsPerModule"
          :key="i"
          class="flex items-center gap-3 rounded-lg p-2"
        >
          <div class="h-4 w-4 shrink-0 animate-pulse rounded bg-gray-200" />
          <div class="h-4 flex-1 max-w-[180px] animate-pulse rounded bg-gray-200" />
          <div class="h-5 w-14 animate-pulse rounded bg-gray-200" />
        </div>
      </div>
    </div>
  </div>
</template>
