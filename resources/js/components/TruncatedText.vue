<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  text: { type: String, default: '' },
  limit: { type: Number, default: 120 },
  maxLines: { type: Number, default: 3 },
  emptyLabel: { type: String, default: 'empty' },
})

const expanded = ref(false)

const isEmpty = computed(() => props.text == null || props.text === '')

const truncated = computed(() => {
  if (isEmpty.value) return { text: props.emptyLabel, needed: false }
  const raw = props.text
  const lines = raw.split('\n')
  const exceedsLines = lines.length > props.maxLines
  const exceedsChars = raw.length > props.limit

  if (!exceedsLines && !exceedsChars) return { text: raw, needed: false }
  if (expanded.value) return { text: raw, needed: true }

  let clipped = raw
  if (exceedsLines) {
    clipped = lines.slice(0, props.maxLines).join('\n')
  }
  if (clipped.length > props.limit) {
    clipped = clipped.slice(0, props.limit)
  }
  return { text: clipped + '...', needed: true }
})

function toggle() {
  expanded.value = !expanded.value
}
</script>

<template>
  <span class="whitespace-pre-wrap break-words">{{ truncated.text }}<button
      v-if="truncated.needed"
      type="button"
      class="ml-1 text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline"
      @click.stop="toggle"
    >{{ expanded ? 'Show less' : 'More' }}</button></span>
</template>
