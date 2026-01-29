<script setup>
    defineProps({fields: Array, modelValue: Object, errors: Object})

    const emit = defineEmits(['update:modelValue'])

    const update = (key, value) => {
    emit('update:modelValue', { ...modelValue, [key]: value })
    }
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <template v-for="f in fields" :key="f.key">

      <Textarea
        v-if="f.type === 'textarea'"
        :label="f.label"
        :modelValue="modelValue[f.key]"
        @update:modelValue="v => update(f.key, v)"
        :error="errors[`meta.${f.key}`]"
      />

      <Select
        v-else-if="f.type === 'select'"
        :label="f.label"
        :options="f.options"
        :modelValue="modelValue[f.key]"
        @update:modelValue="v => update(f.key, v)"
        :error="errors[`meta.${f.key}`]"
      />

      <Checkbox
        v-else-if="f.type === 'checkbox'"
        :label="f.label"
        :modelValue="modelValue[f.key]"
        @update:modelValue="v => update(f.key, v)"
      />

      <Input
        v-else
        :label="f.label"
        :type="f.type"
        :modelValue="modelValue[f.key]"
        @update:modelValue="v => update(f.key, v)"
        :error="errors[`meta.${f.key}`]"
      />

    </template>
  </div>
</template>
