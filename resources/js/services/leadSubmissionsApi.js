import api from '@/lib/axios'

export default {
  getTeamOptions() {
    return api.get('/field-submissions/team-options')
  },
  getCurrentDraft() {
    return api.get('/lead-submissions/current-draft')
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
