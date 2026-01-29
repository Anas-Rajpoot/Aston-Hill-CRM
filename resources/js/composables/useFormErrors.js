import { ref } from 'vue'

export function useFormErrors() {
  const errors = ref({})

  const setErrors = (e) => {
    errors.value = e?.response?.data?.errors || {}
  }

  const clearErrors = () => {
    errors.value = {}
  }

  return { errors, setErrors, clearErrors }
}
