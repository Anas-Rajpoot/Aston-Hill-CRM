<script setup>
/** Base skeleton block for progressive rendering. Use in tables, lists, cards. */
defineProps({
  class: { type: String, default: '' },
  width: { type: String, default: '100%' },
  height: { type: String, default: '1rem' },
})
</script>

<template>
  <div
    class="animate-pulse rounded bg-gray-200"
    :class="$props.class"
    :style="{ width: width, height: height }"
  />
</template>
