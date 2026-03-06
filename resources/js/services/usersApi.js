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

  /** Critical data for initial render (edit page). Use with ?fields[user]=... for sparse fieldsets. */
  prime(id, config = {}) {
    return api.get(`/users/${id}/prime`, config)
  },

  /** Secondary data (roles, managers, team_leaders). */
  extras(id, config = {}) {
    return api.get(`/users/${id}/extras`, config)
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

  bulkAssignMonthlyTarget(data) {
    return api.post('/users/bulk-assign-monthly-target', data)
  },

  // ── New endpoints ──

  /** Bulk export — returns streamed CSV download */
  exportCsv(params = {}) {
    return api.get('/users/export', { params, responseType: 'blob' })
  },

  /** Download import template CSV */
  importTemplate() {
    return api.get('/users/import-template', { responseType: 'blob' })
  },

  /** Bulk import — upload CSV file */
  bulkImport(file) {
    const fd = new FormData()
    fd.append('file', file)
    return api.post('/users/bulk-import', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  /** Update monthly target for a user */
  updateMonthlyTarget(id, data) {
    return api.post(`/users/${id}/monthly-target`, data)
  },

  /** Get monthly target history for a user */
  monthlyTargetHistory(id) {
    return api.get(`/users/${id}/monthly-target-history`).then((r) => r.data)
  },

  /** Request OTP for delete confirmation */
  requestDeleteOtp(id) {
    return api.post(`/users/${id}/request-delete-otp`)
  },

  /** OTP-verified delete */
  otpDelete(id, otp) {
    return api.post(`/users/${id}/otp-delete`, { otp })
  },
}

