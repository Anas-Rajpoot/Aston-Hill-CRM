<script setup>
/**
 * Attendance Log filters bar – fluid layout without horizontal scroller.
 * Employee, Role, Apply/Reset and extra actions should adapt when sidebar toggles.
 */
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue'

defineProps({
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({ users: [], roles: [] }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])

const scroller = ref(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)

function updateScrollState() {
  const el = scroller.value
  if (!el) {
    canScrollLeft.value = false
    canScrollRight.value = false
    return
  }
  canScrollLeft.value = el.scrollLeft > 2
  canScrollRight.value = (el.scrollLeft + el.clientWidth) < (el.scrollWidth - 2)
}

function scrollLeft() {
  scroller.value?.scrollBy({ left: -300, behavior: 'smooth' })
}

function scrollRight() {
  scroller.value?.scrollBy({ left: 300, behavior: 'smooth' })
}

onMounted(() => {
  nextTick(() => {
    updateScrollState()
    const el = scroller.value
    if (!el) return
    el.addEventListener('scroll', updateScrollState, { passive: true })
    window.addEventListener('resize', updateScrollState)
  })
})

onBeforeUnmount(() => {
  const el = scroller.value
  if (el) el.removeEventListener('scroll', updateScrollState)
  window.removeEventListener('resize', updateScrollState)
})
</script>

<template>
  <div class="rounded-lg border border-gray-200 bg-white p-3">
    <div class="relative">
      <button
        type="button"
        class="absolute left-1 top-1/2 z-10 -translate-y-1/2 rounded border border-gray-300 bg-white p-1 text-gray-600 shadow-sm hover:bg-gray-50 disabled:opacity-40 hidden sm:block"
        :disabled="!canScrollLeft"
        @click="scrollLeft"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <div
        ref="scroller"
        class="px-2 sm:px-8"
      >
      <div class="flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-52">
          <label class="block text-xs font-medium text-gray-600">Employee</label>
          <select
            v-model="filters.user_id"
            class="mt-0.5 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          >
            <option value="">All</option>
            <option v-for="u in filterOptions.users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div class="w-full sm:w-52">
          <label class="block text-xs font-medium text-gray-600">Role</label>
          <select
            v-model="filters.role"
            class="mt-0.5 w-full rounded border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          >
            <option value="">All</option>
            <option v-for="r in filterOptions.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center whitespace-nowrap rounded bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover focus:ring-2 focus:ring-brand-primary disabled:opacity-50"
            :disabled="loading"
            @click="$emit('apply')"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Apply
          </button>
          <button
            type="button"
            class="whitespace-nowrap rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="loading"
            @click="$emit('reset')"
          >
            Reset
          </button>
          <slot name="after-reset" />
        </div>
      </div>
      </div>
      <button
        type="button"
        class="absolute right-1 top-1/2 z-10 -translate-y-1/2 rounded border border-gray-300 bg-white p-1 text-gray-600 shadow-sm hover:bg-gray-50 disabled:opacity-40 hidden sm:block"
        :disabled="!canScrollRight"
        @click="scrollRight"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>
  </div>
</template>
