import api from '@/lib/axios'

const TEAM_OPTIONS_TTL_MS = 2 * 60 * 1000  // 2 min
const CURRENT_DRAFT_TTL_MS = 60 * 1000    // 1 min

let _teamOptionsPromise = null
let _teamOptionsAt = 0
let _currentDraftPromise = null
let _currentDraftAt = 0

function getTeamOptionsCached() {
  if (_teamOptionsPromise && Date.now() - _teamOptionsAt < TEAM_OPTIONS_TTL_MS) {
    return _teamOptionsPromise
  }
  _teamOptionsAt = Date.now()
  _teamOptionsPromise = api.get('/field-submissions/team-options')
  return _teamOptionsPromise
}

function getCurrentDraftCached() {
  if (_currentDraftPromise && Date.now() - _currentDraftAt < CURRENT_DRAFT_TTL_MS) {
    return _currentDraftPromise
  }
  _currentDraftAt = Date.now()
  _currentDraftPromise = api.get('/lead-submissions/current-draft')
  return _currentDraftPromise
}

/** Call after creating/updating/discarding a draft so next getCurrentDraft fetches fresh. */
export function invalidateCurrentDraftCache() {
  _currentDraftPromise = null
  _currentDraftAt = 0
}

export default {
  getTeamOptions() {
    return getTeamOptionsCached()
  },
  getCurrentDraft() {
    return getCurrentDraftCached()
  },
  storeStep1(data, draft = false) {
    return api.post('/lead-submissions/step-1', { ...data, draft })
  },
  updateStep1(id, data, draft = false) {
    return api.put(`/lead-submissions/${id}/step-1`, { ...data, draft })
  },
  discardDraft(id) {
    return api.delete(`/lead-submissions/${id}/discard`)
  },
  getCategories() {
    return api.get('/lead-submissions/categories')
  },
  getServiceTypesByCategory(categoryId) {
    return api.get('/lead-submissions/service-types', { params: { service_category_id: categoryId } })
  },
  getLead(id) {
    return api.get(`/lead-submissions/${id}`)
  },
  getTypeSchema(typeId) {
    return api.get(`/lead-submissions/type-schema/${typeId}`)
  },
  storeStep2(id, data) {
    return api.post(`/lead-submissions/${id}/step-2`, data)
  },
  storeStep3(id, data) {
    return api.post(`/lead-submissions/${id}/step-3`, data)
  },
  storeStep4(id, data) {
    return api.post(`/lead-submissions/${id}/step-4`, data)
  },
  submit(id) {
    return api.post(`/lead-submissions/${id}/submit`)
  },
}
