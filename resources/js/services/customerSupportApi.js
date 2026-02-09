import api from '@/lib/axios'

export default {
  getTeamOptions() {
    return api.get('/customer-support/team-options')
  },

  async index(params = {}) {
    const { data } = await api.get('/customer-support', { params })
    return data
  },
  async filters() {
    const { data } = await api.get('/customer-support/filters')
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
  /** Partial update for listing inline edits. */
  async updateSubmissionFields(submissionId, payload) {
    const { data } = await api.patch(`/customer-support/${submissionId}`, payload)
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
      if (data[k] != null && data[k] !== '') formData.append(k, data[k])
    })
    formData.append('submit', submit ? '1' : '0')
    if (data.attachment_1 instanceof File) formData.append('attachment_1', data.attachment_1)
    if (data.attachment_2 instanceof File) formData.append('attachment_2', data.attachment_2)
    return api.post('/customer-support', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
