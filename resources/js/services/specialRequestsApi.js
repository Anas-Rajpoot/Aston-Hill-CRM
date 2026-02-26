import api from '@/lib/axios'

export default {
  async store(formData) {
    const { data } = await api.post('/special-requests', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },

  async getRequest(id) {
    const { data } = await api.get(`/special-requests/${id}`)
    return data
  },

  async index(params = {}, options = {}) {
    const { data } = await api.get('/special-requests', { params, signal: options.signal })
    return data
  },

  async filters() {
    const { data } = await api.get('/special-requests/filters')
    return data
  },

  async columns() {
    const { data } = await api.get('/special-requests/columns')
    return data
  },

  async saveColumns(visibleColumns) {
    const { data } = await api.post('/special-requests/columns', {
      visible_columns: visibleColumns,
    })
    return data
  },

  async updateRequest(id, payload) {
    const { data } = await api.put(`/special-requests/${id}`, payload)
    return data
  },

  async patchRequest(id, payload) {
    const { data } = await api.patch(`/special-requests/${id}`, payload)
    return data
  },

  async getAudits(id) {
    const { data } = await api.get(`/special-requests/${id}/audits`)
    return data
  },

  async downloadDocument(requestId, documentId) {
    const { data } = await api.get(
      `/special-requests/${requestId}/documents/${documentId}/download`,
      { responseType: 'blob' }
    )
    return data
  },

  async uploadDocuments(requestId, formData) {
    const { data } = await api.post(`/special-requests/${requestId}/documents`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },

  async deleteDocument(requestId, documentId) {
    const { data } = await api.delete(`/special-requests/${requestId}/documents/${documentId}`)
    return data
  },

  getTeamOptions() {
    return api.get('/field-submissions/team-options')
  },
}
