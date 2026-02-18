<template>
  <router-link
    :to="to"
    class="group relative block rounded-md text-sm font-medium transition-colors"
    :class="[linkClasses, collapsed ? 'flex items-center justify-center px-0 py-2' : 'px-3 py-2']"
    :title="collapsed ? label : undefined"
  >
    <span v-if="collapsed" class="text-xs font-bold leading-none">{{ abbr }}</span>
    <span v-else>{{ label }}</span>

    <!-- Tooltip on hover when collapsed -->
    <span
      v-if="collapsed"
      class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded bg-gray-800 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
    >{{ label }}</span>
  </router-link>
</template>

<script setup>
import { useRoute } from 'vue-router'
import { computed, toRefs } from 'vue'

const props = defineProps({
  to: { type: String, required: true },
  label: { type: String, default: '' },
  abbr: { type: String, default: '' },
  collapsed: { type: Boolean, default: false },
  dark: { type: Boolean, default: false },
  sub: { type: Boolean, default: false },
  active: { type: Boolean, default: null },
})

const route = useRoute()
const { to, dark, sub, active } = toRefs(props)

const computedActive = computed(() => {
  if (to.value === '/') return route.path === '/' || route.path === '/dashboard'
  return route.path.startsWith(to.value)
})

const isActive = computed(() => active.value !== null ? active.value : computedActive.value)

const linkClasses = computed(() => {
  if (dark.value) {
    return isActive.value
      ? 'bg-lime-600/20 text-lime-400'
      : 'text-gray-300 hover:bg-gray-800 hover:text-white'
  }
  if (sub.value) {
    return isActive.value
      ? 'bg-gray-800 text-lime-400'
      : 'text-gray-400 hover:bg-gray-800 hover:text-white'
  }
  return isActive.value
    ? 'bg-indigo-50 text-indigo-700'
    : 'text-gray-700 hover:bg-gray-50'
})
</script>
