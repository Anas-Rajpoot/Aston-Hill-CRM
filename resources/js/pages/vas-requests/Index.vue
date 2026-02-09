<script setup>
    import { ref, reactive, onMounted } from 'vue'
    import vasRequestsApi from '@/services/vasRequestsApi'

    const loading = ref(false)
    const rows = ref([])
    const pagination = ref({})

    const filters = reactive({
        q: '',
        status: '',
        request_type: '',
        from: '',
        to: '',
    })

    const load = async (page = 1) => {
        loading.value = true
        const { data } = await vasRequestsApi.list({ ...filters, page })
        rows.value = data.data
        pagination.value = data.meta
        loading.value = false
    }

    onMounted(load)
</script>

<template>
  <div>
    <AdvancedFilters v-model="filters" @apply="load" />
    <VasTable :rows="rows" :loading="loading" />
  </div>
</template>
