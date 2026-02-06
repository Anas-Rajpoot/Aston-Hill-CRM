import api from '@/lib/axios'

export default {
  getTeamOptions() {
    return api.get('/field-submissions/team-options')
  },
  store(data, submit = true) {
    return api.post('/field-submissions', { ...data, submit })
  },

  async index(params = {}) {
    const { data } = await api.get('/field-submissions', { params })
    return data
  },
  async filters() {
    const { data } = await api.get('/field-submissions/filters')
    return data
  },
  async columns() {
    const { data } = await api.get('/field-submissions/columns')
    return data
  },
  async saveColumns(visibleColumns) {
    const { data } = await api.post('/field-submissions/columns', {
      visible_columns: visibleColumns,
    })
    return data
  },
  async updateStatus(fieldSubmissionId, status) {
    const { data } = await api.patch(`/field-submissions/${fieldSubmissionId}/status`, { status })
    return data
  },

  async assignFieldTechnician(fieldSubmissionId, fieldExecutiveId) {
    const { data } = await api.patch(
      `/field-submissions/${fieldSubmissionId}/assign-field-technician`,
      { field_executive_id: fieldExecutiveId }
    )
    return data
  },

  async getSubmission(id) {
    const { data } = await api.get(`/field-submissions/${id}`)
    return data
  },

  async updateSubmission(id, payload, files = null) {
    if (files && files.length > 0) {
      const form = new FormData()
      Object.entries(payload).forEach(([k, v]) => {
        form.append(k, v != null && v !== '' ? v : '')
      })
      files.forEach((f) => form.append('documents[]', f))
      const { data } = await api.put(`/field-submissions/${id}`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return data
    }
    const { data } = await api.put(`/field-submissions/${id}`, payload)
    return data
  },

  async getEditOptions() {
    const { data } = await api.get('/field-submissions/edit-options')
    return data
  },

  async downloadDocument(submissionId, documentId) {
    const { data } = await api.get(
      `/field-submissions/${submissionId}/documents/${documentId}/download`,
      { responseType: 'blob' }
    )
    return data
  },
}
