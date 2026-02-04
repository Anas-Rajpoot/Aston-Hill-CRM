<script setup>
const props = defineProps({
  meta: Object,
})

const emit = defineEmits(['change'])
</script>

<template>
  <div v-if="meta" class="flex items-center gap-2">
    <button
      type="button"
      :disabled="!meta.prev_page_url"
      class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
      @click="emit('change', meta.current_page - 1)"
    >
      Previous
    </button>
    <span class="rounded border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">
      Page {{ meta.current_page }} of {{ meta.last_page }}
    </span>
    <button
      type="button"
      :disabled="!meta.next_page_url"
      class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
      @click="emit('change', meta.current_page + 1)"
    >
      Next
    </button>
  </div>
</template>
