import api from '@/lib/axios'

export default {
  getTeamOptions() {
    return api.get('/vas-requests/team-options')
  },
  getDocumentSchema() {
    return api.get('/vas-requests/document-schema')
  },
  storeStep1(data) {
    return api.post('/vas-requests/step-1', data)
  },
  getRequest(id) {
    return api.get(`/vas-requests/${id}`)
  },
  storeStep2(id, formData) {
    return api.post(`/vas-requests/${id}/step-2`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  submit(id) {
    return api.post(`/vas-requests/${id}/submit`)
  },
  list(params) {
    return api.get('/vas-requests', { params })
  },
}
