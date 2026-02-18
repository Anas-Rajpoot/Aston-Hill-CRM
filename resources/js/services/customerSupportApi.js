import api from '@/lib/axios'

export default {
  getTeamOptions() {
    return api.get('/customer-support/team-options')
  },

  async index(params = {}, options = {}) {
    const { data } = await api.get('/customer-support', { params, signal: options.signal })
    return data
  },

  async getSubmission(id) {
    const { data } = await api.get(`/customer-support/${id}`)
    return data
  },
  async filters() {
    const { data } = await api.get('/customer-support/filters')
    return data
  },
  async getEditOptions() {
    const { data } = await api.get('/customer-support/edit-options')
    return data
  },
  async getAudits(submissionId) {
    const { data } = await api.get(`/customer-support/${submissionId}/audits`)
    return data
  },
  async columns() {
    const { data } = await api.get('/customer-support/columns')
    return data
  },
  async saveColumns(visibleColumns) {
    const { data } = await api.post('/customer-support/columns', {
      visible_columns: visibleColumns,
    })
    return data
  },
  /** Partial update for listing inline edits or full edit form. */
  async updateSubmission(submissionId, payload) {
    const { data } = await api.patch(`/customer-support/${submissionId}`, payload)
    return data
  },
  /** Alias for listing inline edits. */
  async updateSubmissionFields(submissionId, payload) {
    return this.updateSubmission(submissionId, payload)
  },

  /** Upload additional attachments (append) for a submission. FormData with files. */
  async uploadAttachments(submissionId, files) {
    const formData = new FormData()
    if (Array.isArray(files)) {
      files.forEach((f, i) => formData.append(`document_${i + 1}`, f))
    } else {
      formData.append('document_1', files)
    }
    const { data } = await api.post(`/customer-support/${submissionId}/attachments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },

  async getCsrOptions() {
    const { data } = await api.get('/customer-support/csr-options')
    return data
  },

  async bulkAssign(submissionIds, payload) {
    const { data } = await api.post('/customer-support/bulk-assign', {
      submission_ids: submissionIds,
      csr_id: payload.csr_id,
    })
    return data
  },

  async resubmit(id, payload) {
    const { data } = await api.post(`/customer-support/${id}/resubmit`, payload)
    return data
  },

  async assignCsr(submissionId, csrId) {
    const { data } = await api.patch(`/customer-support/${submissionId}/assign-csr`, {
      csr_id: csrId,
    })
    return data
  },

  store(data, submit = true) {
    const formData = new FormData()
    const keys = [
      'issue_category',
      'company_name',
      'account_number',
      'contact_number',
      'issue_description',
      'manager_id',
      'team_leader_id',
      'sales_agent_id',
    ]
    keys.forEach((k) => {
      const v = data[k]
      if (v != null && v !== '' && (typeof v !== 'number' || (v >= 1 && Number.isInteger(v)))) formData.append(k, v)
    })
    formData.append('submit', submit ? '1' : '0')
    if (data.attachment_1 instanceof File) formData.append('attachment_1', data.attachment_1)
    if (data.attachment_2 instanceof File) formData.append('attachment_2', data.attachment_2)
    return api.post('/customer-support', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
