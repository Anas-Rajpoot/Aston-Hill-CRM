/**
 * useFormDraft(module, recordRef, formReactive, options?)
 *
 * Drop-in composable for auto-saving form drafts.
 *
 * Usage:
 *   const form = reactive({ name: '', email: '' })
 *   const { draftLoaded, draftSaving, draftSavedAt, clearDraft } = useFormDraft('lead-submission', 'new', form)
 *
 * Behaviour:
 *  - On mount: fetches any existing draft and merges into form (only if field is empty/default)
 *  - Debounced auto-save every 3s after changes
 *  - Saves on field blur via draftNow()
 *  - Saves on beforeunload
 *  - clearDraft() should be called after successful form submit
 *  - Does nothing if auto_save_draft_forms is disabled server-side
 */
import { ref, watch, onMounted, onBeforeUnmount, isRef, toRaw, unref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import api from '@/lib/axios'

const DEBOUNCE_MS = 3000

export function useFormDraft(module, recordRef, formReactive, options = {}) {
  const draftEnabled  = ref(false)
  const draftLoaded   = ref(false)
  const draftSaving   = ref(false)
  const draftSavedAt  = ref(null)
  const draftError    = ref(null)

  let debounceTimer   = null
  let lastSnapshot    = ''
  let etag            = null
  const refKey        = isRef(recordRef) ? recordRef : ref(recordRef)
  const getForm       = () => unref(formReactive)

  // ── Snapshot helpers ──────────────────────────────────
  function snapshot() {
    return JSON.stringify(toRaw(getForm()))
  }

  function hasChanges() {
    return snapshot() !== lastSnapshot
  }

  // ── Fetch existing draft ──────────────────────────────
  async function loadDraft() {
    try {
      const headers = etag ? { 'If-None-Match': etag } : {}
      const res = await api.get(`/form-drafts/${module}/${refKey.value}`, {
        headers,
        validateStatus: s => s === 200 || s === 304,
      })

      if (res.status === 304) {
        draftLoaded.value = true
        return
      }

      draftEnabled.value = res.data?.enabled ?? false
      if (!draftEnabled.value) { draftLoaded.value = true; return }

      etag = res.headers?.etag || null

      const saved = res.data?.data
      if (saved && typeof saved === 'object') {
        const form = getForm()
        if (!form || typeof form !== 'object') return
        // Merge draft into form — only fill empty/default fields
        Object.keys(saved).forEach(k => {
          if (k in form) {
            const cur = form[k]
            // Fill if field is empty, null, or default empty array
            if (cur === '' || cur === null || cur === undefined || (Array.isArray(cur) && cur.length === 0)) {
              form[k] = saved[k]
            }
          }
        })
      }
    } catch {
      // silent — draft is optional
    } finally {
      draftLoaded.value = true
      lastSnapshot = snapshot()
    }
  }

  // ── Save draft ────────────────────────────────────────
  async function saveDraft() {
    if (!draftEnabled.value || !hasChanges()) return

    draftSaving.value = true
    draftError.value  = null

    try {
      const data = toRaw(getForm())
      await api.post(`/form-drafts/${module}/${refKey.value}`, { data })
      lastSnapshot = snapshot()
      draftSavedAt.value = new Date()
    } catch (e) {
      draftError.value = e?.message
    } finally {
      draftSaving.value = false
    }
  }

  function draftNow() {
    clearTimeout(debounceTimer)
    saveDraft()
  }

  function scheduleSave() {
    if (!draftEnabled.value) return
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(saveDraft, DEBOUNCE_MS)
  }

  // ── Clear draft (call after successful submit) ────────
  async function clearDraft() {
    clearTimeout(debounceTimer)
    try {
      await api.delete(`/form-drafts/${module}/${refKey.value}`)
    } catch { /* silent */ }
    draftSavedAt.value = null
    lastSnapshot = snapshot()
  }

  // ── Watch form changes ────────────────────────────────
  watch(
    () => snapshot(),
    () => { scheduleSave() },
    { flush: 'post' }
  )

  // ── Lifecycle ─────────────────────────────────────────
  function beforeUnloadHandler() {
    if (draftEnabled.value && hasChanges()) {
      // Use sendBeacon for reliable save on page close
      const url = `/api/form-drafts/${module}/${refKey.value}`
      const blob = new Blob(
        [JSON.stringify({ data: toRaw(getForm()) })],
        { type: 'application/json' }
      )
      navigator.sendBeacon(url, blob)
    }
  }

  onMounted(() => {
    loadDraft()
    window.addEventListener('beforeunload', beforeUnloadHandler)
  })

  onBeforeUnmount(() => {
    clearTimeout(debounceTimer)
    window.removeEventListener('beforeunload', beforeUnloadHandler)
    // Final save on unmount
    if (draftEnabled.value && hasChanges()) {
      saveDraft()
    }
  })

  // Route leave guard
  try {
    onBeforeRouteLeave((to, from, next) => {
      if (draftEnabled.value && hasChanges()) {
        draftNow()
      }
      next()
    })
  } catch {
    // Not inside router context — ignore
  }

  return {
    draftEnabled,
    draftLoaded,
    draftSaving,
    draftSavedAt,
    draftError,
    draftNow,       // trigger immediate save (e.g., on blur)
    clearDraft,     // call after successful submit
  }
}
