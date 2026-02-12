import api from '@/lib/axios'

export default {
  index(params = {}) {
    return api.get('/cisco-extensions', { params })
  },

  filters() {
    return api.get('/cisco-extensions/filters')
  },

  summary() {
    return api.get('/cisco-extensions/summary')
  },

  columns() {
    return api.get('/cisco-extensions/columns')
  },

  saveColumns(visibleColumns) {
    return api.post('/cisco-extensions/columns', { visible_columns: visibleColumns })
  },

  show(id) {
    return api.get(`/cisco-extensions/${id}`)
  },

  create(payload) {
    return api.post('/cisco-extensions', payload)
  },

  bulkImport(file) {
    const formData = new FormData()
    formData.append('file', file)
    return api.post('/cisco-extensions/bulk-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  update(id, payload) {
    return api.put(`/cisco-extensions/${id}`, payload)
  },

  patch(id, payload) {
    return api.patch(`/cisco-extensions/${id}`, payload)
  },

  getAuditLog(id) {
    return api.get(`/cisco-extensions/${id}/audit-log`)
  },

  getAssignableEmployees() {
    return api.get('/cisco-extensions/assignable-employees')
  },

  destroy(id) {
    return api.delete(`/cisco-extensions/${id}`)
  },
}
