import api from '@/lib/axios'

export default {
  async index(params = {}) {
    const { data } = await api.get('/email-follow-ups', { params })
    return data
  },
  async filters() {
    const { data } = await api.get('/email-follow-ups/filters')
    return data
  },
  async columns() {
    const { data } = await api.get('/email-follow-ups/columns')
    return data
  },
  async saveColumns(visibleColumns) {
    const { data } = await api.post('/email-follow-ups/columns', {
      visible_columns: visibleColumns,
    })
    return data
  },
  async store(payload) {
    const { data } = await api.post('/email-follow-ups', payload)
    return data
  },
  async patch(id, payload) {
    const { data } = await api.patch(`/email-follow-ups/${id}`, payload)
    return data
  },
  async updateStatus(id, status) {
    const { data } = await api.patch(`/email-follow-ups/${id}/status`, { status })
    return data
  },
  async bulkAction(payload) {
    const { data } = await api.post('/email-follow-ups/bulk-action', payload)
    return data
  },
}
