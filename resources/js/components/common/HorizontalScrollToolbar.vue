<script setup>
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  step: { type: Number, default: 320 },
})

const scroller = ref(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)
let resizeObserver = null

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
  scroller.value?.scrollBy({ left: -props.step, behavior: 'smooth' })
}

function scrollRight() {
  scroller.value?.scrollBy({ left: props.step, behavior: 'smooth' })
}

onMounted(() => {
  nextTick(() => {
    updateScrollState()
    const el = scroller.value
    if (!el) return
    el.addEventListener('scroll', updateScrollState, { passive: true })
    window.addEventListener('resize', updateScrollState)

    if (typeof ResizeObserver !== 'undefined') {
      resizeObserver = new ResizeObserver(() => updateScrollState())
      resizeObserver.observe(el)
      if (el.firstElementChild) resizeObserver.observe(el.firstElementChild)
    }
  })
})

onBeforeUnmount(() => {
  const el = scroller.value
  if (el) el.removeEventListener('scroll', updateScrollState)
  window.removeEventListener('resize', updateScrollState)
  if (resizeObserver) resizeObserver.disconnect()
})
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="absolute left-1 top-1/2 z-10 -translate-y-1/2 rounded border border-gray-300 bg-white p-1 text-gray-600 shadow-sm hover:bg-gray-50 disabled:opacity-40"
      :disabled="!canScrollLeft"
      @click="scrollLeft"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
    </button>

    <div
      ref="scroller"
      class="overflow-x-auto overflow-y-hidden px-8 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
    >
      <div class="inline-flex min-w-max items-center gap-2 whitespace-nowrap">
        <slot />
      </div>
    </div>

    <button
      type="button"
      class="absolute right-1 top-1/2 z-10 -translate-y-1/2 rounded border border-gray-300 bg-white p-1 text-gray-600 shadow-sm hover:bg-gray-50 disabled:opacity-40"
      :disabled="!canScrollRight"
      @click="scrollRight"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </button>
  </div>
</template>
