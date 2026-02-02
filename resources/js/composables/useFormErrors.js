import { ref } from 'vue'

/**
 * Extract validation errors and general message from axios error.
 * Laravel returns { message, errors: { field: ['msg'] } } on 422.
 */
export function useFormErrors() {
  const errors = ref({})
  const generalMessage = ref('')

  const setErrors = (e) => {
    const data = e?.response?.data
    errors.value = data?.errors || {}
    generalMessage.value = data?.message || (e?.response?.status === 422 ? 'Please correct the errors below.' : (e?.message || 'An error occurred.'))
  }

  const clearErrors = () => {
    errors.value = {}
    generalMessage.value = ''
  }

  /** Clear error for a specific field when user corrects it */
  const clearFieldError = (field) => {
    if (errors.value[field]) {
      const next = { ...errors.value }
      delete next[field]
      errors.value = next
      if (Object.keys(next).length === 0) generalMessage.value = ''
    }
  }

  /** Get first error message for a field (handles array or string) */
  const getError = (field) => {
    const err = errors.value[field]
    if (!err) return null
    return Array.isArray(err) ? err[0] : err
  }

  return { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError }
}
