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
  vas: 'heart',
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
      <!-- Heart icon (VAS Request) – custom VAS icon -->
      <svg v-else-if="icons[tab.key] === 'heart'" class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
      </svg>
      <span>{{ tab.label }}</span>
    </button>
  </div>
</template>
