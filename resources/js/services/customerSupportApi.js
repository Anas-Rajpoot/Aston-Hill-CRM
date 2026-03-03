import api from '@/lib/axios'

let _csrOptionsCache = null
let _csrOptionsCacheAt = 0
const CSR_OPTIONS_TTL_MS = 5 * 60 * 1000

let _teamOptionsCache = null
let _teamOptionsCacheAt = 0
const TEAM_OPTIONS_TTL_MS = 2 * 60 * 1000

export default {
  getTeamOptions(forceRefresh = false) {
    if (!forceRefresh && _teamOptionsCache && Date.now() - _teamOptionsCacheAt < TEAM_OPTIONS_TTL_MS) {
      return Promise.resolve(_teamOptionsCache)
    }
    const req = api.get('/customer-support/team-options')
    req.then((res) => {
      _teamOptionsCache = res
      _teamOptionsCacheAt = Date.now()
    })
    return req
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

  async getCsrsByAccount(accountNumber) {
    if (!accountNumber) return { csr_ids: [] }
    const { data } = await api.get('/customer-support/csrs-by-account', { params: { account_number: accountNumber } })
    return data
  },

  async getCsrOptions() {
    if (_csrOptionsCache && Date.now() - _csrOptionsCacheAt < CSR_OPTIONS_TTL_MS) {
      return _csrOptionsCache
    }
    const { data } = await api.get('/customer-support/csr-options')
    _csrOptionsCache = data
    _csrOptionsCacheAt = Date.now()
    return data
  },

  async bulkAssign(submissionIds, payload) {
    const { data } = await api.post('/customer-support/bulk-assign', {
      submission_ids: submissionIds,
      csr_id: payload.csr_id,
    })
    return data
  },

  async bulkAssignStatus(trackingId) {
    const { data } = await api.get(`/customer-support/bulk-assign/${trackingId}/status`)
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

  async destroy(submissionId) {
    const { data } = await api.delete(`/customer-support/${submissionId}`)
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
