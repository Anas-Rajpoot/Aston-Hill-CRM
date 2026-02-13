import api from '@/lib/axios'

export default {
  index(params = {}) {
    return api.get('/users', { params })
  },

  filters() {
    return api.get('/users/filters').then((r) => r.data)
  },

  columns() {
    return api.get('/users/columns').then((r) => r.data)
  },

  saveColumns(visibleColumns) {
    return api.post('/users/columns', { visible_columns: visibleColumns }).then((r) => r.data)
  },

  store(data) {
    return api.post('/users', data)
  },

  show(id) {
    return api.get(`/users/${id}`)
  },

  update(id, data) {
    return api.put(`/users/${id}`, data)
  },

  patch(id, field, value) {
    return api.patch(`/users/${id}`, { field, value }).then((r) => r.data)
  },

  auditLog(id) {
    return api.get(`/users/${id}/audit-log`).then((r) => r.data)
  },

  sendPasswordReset(id) {
    return api.post(`/users/${id}/send-password-reset`).then((r) => r.data)
  },

  destroy(id) {
    return api.delete(`/users/${id}`)
  },

  bulkActivate(ids) {
    return api.post('/users/bulk-activate', { ids })
  },

  bulkDeactivate(ids) {
    return api.post('/users/bulk-deactivate', { ids })
  },
}

