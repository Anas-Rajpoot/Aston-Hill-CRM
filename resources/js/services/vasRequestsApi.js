import api from '@/lib/axios'

let _vasBackOfficeOptionsCache = null
let _vasBackOfficeOptionsCacheAt = 0
const VAS_BACK_OFFICE_OPTIONS_TTL_MS = 5 * 60 * 1000

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
  updateStep1(id, data) {
    return api.put(`/vas-requests/${id}`, data)
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

  async index(params = {}, options = {}) {
    const { data } = await api.get('/vas-requests', { params, signal: options.signal })
    return data
  },
  async filters() {
    const { data } = await api.get('/vas-requests/filters')
    return data
  },
  async columns() {
    const { data } = await api.get('/vas-requests/columns')
    return data
  },
  async saveColumns(visibleColumns) {
    const { data } = await api.post('/vas-requests/columns', {
      visible_columns: visibleColumns,
    })
    return data
  },
  async updateSubmissionFields(submissionId, payload) {
    const { data } = await api.patch(`/vas-requests/${submissionId}`, payload)
    return data
  },
  async updateRequest(id, payload) {
    const { data } = await api.put(`/vas-requests/${id}`, payload)
    return data
  },

  async getBackOfficeOptions() {
    if (_vasBackOfficeOptionsCache && Date.now() - _vasBackOfficeOptionsCacheAt < VAS_BACK_OFFICE_OPTIONS_TTL_MS) {
      return _vasBackOfficeOptionsCache
    }
    const { data } = await api.get('/vas-requests/back-office-options')
    _vasBackOfficeOptionsCache = data
    _vasBackOfficeOptionsCacheAt = Date.now()
    return data
  },

  async assignBackOffice(vasId, payload) {
    const { data } = await api.patch(`/vas-requests/${vasId}`, {
      back_office_executive_id: payload.executive_id ?? payload.back_office_executive_id ?? null,
    })
    return data
  },

  async bulkAssign(vasRequestIds, payload) {
    const { data } = await api.post('/vas-requests/bulk-assign', {
      vas_request_ids: vasRequestIds,
      executive_id: payload.executive_id,
    })
    return data
  },

  async bulkAssignStatus(trackingId) {
    const { data } = await api.get(`/vas-requests/bulk-assign/${trackingId}/status`)
    return data
  },

  async resubmit(id, payload) {
    const { data } = await api.post(`/vas-requests/${id}/resubmit`, payload)
    return data
  },

  async getAudits(vasRequestId) {
    const { data } = await api.get(`/vas-requests/${vasRequestId}/audits`)
    return data
  },

  async downloadDocument(vasRequestId, documentId) {
    const { data } = await api.get(
      `/vas-requests/${vasRequestId}/documents/${documentId}/download`,
      { responseType: 'blob' }
    )
    return data
  },

  async deleteDocument(vasRequestId, documentId) {
    const { data } = await api.delete(`/vas-requests/${vasRequestId}/documents/${documentId}`)
    return data
  },
}
