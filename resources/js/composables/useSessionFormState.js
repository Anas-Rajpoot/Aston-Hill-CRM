import { onMounted, toRaw, unref, watch } from 'vue'

function toPlainObject(value) {
  const raw = toRaw(unref(value))
  if (!raw || typeof raw !== 'object') return {}
  try {
    return JSON.parse(JSON.stringify(raw))
  } catch (_) {
    return {}
  }
}

export function useSessionFormState(storageKey, formRef, options = {}) {
  const omitKeys = Array.isArray(options.omitKeys) ? options.omitKeys : []
  let restoring = false

  function sanitizeForSave(obj) {
    const out = {}
    Object.entries(obj || {}).forEach(([k, v]) => {
      if (omitKeys.includes(k)) return
      // Skip File/Blob-like values; browser cannot restore these securely.
      if (v instanceof File || v instanceof Blob) return
      out[k] = v
    })
    return out
  }

  function persistNow() {
    if (restoring) return
    try {
      const data = sanitizeForSave(toPlainObject(formRef))
      sessionStorage.setItem(storageKey, JSON.stringify(data))
    } catch (_) {
      // best-effort only
    }
  }

  function restoreState() {
    try {
      const raw = sessionStorage.getItem(storageKey)
      if (!raw) return
      const parsed = JSON.parse(raw)
      if (!parsed || typeof parsed !== 'object') return
      const target = unref(formRef)
      if (!target || typeof target !== 'object') return
      restoring = true
      Object.keys(target).forEach((key) => {
        if (Object.prototype.hasOwnProperty.call(parsed, key)) {
          target[key] = parsed[key]
        }
      })
    } catch (_) {
      // ignore corrupted data
    } finally {
      restoring = false
    }
  }

  function clearState() {
    try {
      sessionStorage.removeItem(storageKey)
    } catch (_) {
      // best-effort only
    }
  }

  watch(
    () => toPlainObject(formRef),
    () => {
      persistNow()
    },
    { deep: true, flush: 'post' }
  )

  onMounted(() => {
    if (options.restoreOnMount) restoreState()
  })

  return {
    restoreState,
    persistNow,
    clearState,
  }
}
