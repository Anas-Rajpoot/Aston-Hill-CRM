<script setup>
defineProps({
  tabs: { type: Array, required: true },
  active: { type: String, required: true },
})

const emit = defineEmits(['change'])
</script>

<template>
  <div class="flex gap-1">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      type="button"
      @click="emit('change', tab.key)"
      class="px-5 py-2.5 rounded-full text-sm font-medium transition flex items-center gap-2"
      :class="active === tab.key
        ? 'bg-green-500 text-white shadow-sm'
        : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
    >
      <span>{{ tab.label }}</span>
      <span
        v-if="tab.count != null"
        class="min-w-[1.25rem] h-5 px-1.5 rounded-full flex items-center justify-center text-xs font-medium"
        :class="active === tab.key ? 'bg-green-600/80 text-white' : 'bg-gray-300 text-gray-600'"
      >
        {{ tab.count }}
      </span>
    </button>
  </div>
</template>
