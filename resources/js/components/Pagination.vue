<script setup>
const props = defineProps({
  meta: Object
})

const emit = defineEmits(['change'])
</script>

<template>
  <div class="pagination">
    <button
      :disabled="!meta.prev_page_url"
      @click="emit('change', meta.current_page - 1)"
    >
      Prev
    </button>

    Page {{ meta.current_page }} of {{ meta.last_page }}

    <button
      :disabled="!meta.next_page_url"
      @click="emit('change', meta.current_page + 1)"
    >
      Next
    </button>
  </div>
</template>
