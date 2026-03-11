import api from '@/lib/axios'

export default {
  index(params = {}) {
    return api.get('/expenses', { params })
  },

  summary() {
    return api.get('/expenses/summary')
  },

  filters() {
    return api.get('/expenses/filters')
  },

  columns() {
    return api.get('/expenses/columns')
  },

  saveColumns(visibleColumns) {
    return api.post('/expenses/columns', { visible_columns: visibleColumns })
  },

  destroy(id) {
    return api.delete(`/expenses/${id}`)
  },

  create(payload) {
    if (payload instanceof FormData) {
      return api.post('/expenses', payload)
    }
    return api.post('/expenses', payload)
  },

  importCsv(formData) {
    return api.post('/expenses/import-csv', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  show(id) {
    return api.get(`/expenses/${id}`)
  },

  update(id, payload) {
    return api.put(`/expenses/${id}`, payload)
  },

  getAuditLog(id) {
    return api.get(`/expenses/${id}/audit-log`)
  },

  deleteAttachment(expenseId, attachmentId) {
    return api.delete(`/expenses/${expenseId}/attachments/${attachmentId}`)
  },

  addAttachments(expenseId, formData) {
    return api.post(`/expenses/${expenseId}/attachments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
