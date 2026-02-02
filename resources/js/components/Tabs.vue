<script setup>
defineProps({
  tabs: { type: Array, required: true },
  active: { type: String, required: true },
})

const emit = defineEmits(['change'])

const icons = {
  lead: 'document',
  field: 'map-pin',
  support: 'headset',
  vas: 'diamond',
}
</script>

<template>
  <div class="flex gap-1 bg-[#121d2c] rounded-full p-2 w-full">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      type="button"
      @click="emit('change', tab.key)"
      class="px-5 py-2.5 rounded-full text-sm font-medium transition flex items-center gap-2.5"
      :class="active === tab.key
        ? 'bg-[#6cc04a] text-white shadow-sm ring-1 ring-white/30'
        : 'text-white hover:bg-white/10'"
    >
      <!-- Document with equals sign (Lead) – 2nd image style -->
      <svg v-if="icons[tab.key] === 'document'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <!-- Map pin / location marker (Field) – 2nd image style -->
      <svg v-else-if="icons[tab.key] === 'map-pin'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      <!-- Headset / person with headset (Customer Support) – 2nd image style -->
      <svg v-else-if="icons[tab.key] === 'headset'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 18v-6a9 9 0 0118 0v6" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 19a2 2 0 01-2 2h-1a2 2 0 01-2-2v-3a2 2 0 012-2h3zM3 19a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H3z" />
      </svg>
      <!-- Diamond outline (VAS) – 2nd image style -->
      <svg v-else-if="icons[tab.key] === 'diamond'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L2 12l10 10 10-10L12 2z" />
      </svg>
      <span>{{ tab.label }}</span>
    </button>
  </div>
</template>
