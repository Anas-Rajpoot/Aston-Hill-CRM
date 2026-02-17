import api from '@/lib/axios'

export default {
  async index(params = {}) {
    const { data } = await api.get('/employees', { params })
    return data
  },

  async filters() {
    const { data } = await api.get('/employees/filters')
    return data
  },

  async columns() {
    const { data } = await api.get('/employees/columns')
    return data
  },

  async saveColumns(visibleColumns) {
    const { data } = await api.post('/employees/columns', {
      visible_columns: visibleColumns,
    })
    return data
  },

  async bulkImport(file) {
    const formData = new FormData()
    formData.append('file', file)
    const { data } = await api.post('/employees/bulk-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },

  async getAudits(userId) {
    const { data } = await api.get(`/users/${userId}/audit-log`)
    return data
  },
}
