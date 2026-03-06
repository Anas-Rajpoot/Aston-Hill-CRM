<script setup>
/** Table skeleton for section-level loading. Matches typical data table shape. */
defineProps({
  rows: { type: Number, default: 6 },
  cols: { type: Number, default: 5 },
})
</script>

<template>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
      <div class="h-5 w-32 animate-pulse rounded bg-gray-200" />
    </div>
    <table class="min-w-full">
      <thead class="bg-brand-primary">
        <tr>
          <th v-for="c in cols" :key="c" class="px-4 py-3 text-left">
            <div class="h-4 w-24 animate-pulse rounded bg-gray-200" />
          </th>
          <th class="w-16 px-4 py-3" />
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <tr v-for="r in rows" :key="r" class="hover:bg-gray-50/50">
          <td v-for="c in cols" :key="c" class="px-4 py-3">
            <div
              :class="c === 1 ? 'w-32' : 'w-20'"
              class="h-4 animate-pulse rounded bg-gray-200"
            />
          </td>
          <td class="px-4 py-3">
            <div class="h-8 w-8 animate-pulse rounded bg-gray-200" />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
