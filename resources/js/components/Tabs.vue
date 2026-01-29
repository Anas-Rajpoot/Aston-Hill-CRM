<script setup>
    defineProps({tabs: Array, active: String})

    const emit = defineEmits(['change'])
</script>

<template>
  <div class="flex gap-2">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      @click="emit('change', tab.key)"
      class="px-4 py-2 rounded-full text-sm transition"
      :class="active === tab.key
        ? 'bg-lime-500 text-white'
        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
    >
      {{ tab.label }}
    </button>
  </div>
</template>
