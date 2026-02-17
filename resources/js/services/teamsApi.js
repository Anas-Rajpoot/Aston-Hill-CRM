import api from '@/lib/axios'

export default {
  index(params = {}) {
    return api.get('/teams', { params }).then((r) => r.data)
  },

  filters() {
    return api.get('/teams/filters').then((r) => r.data)
  },

  columns() {
    return api.get('/teams/columns').then((r) => r.data)
  },

  saveColumns(visibleColumns) {
    return api.post('/teams/columns', { visible_columns: visibleColumns }).then((r) => r.data)
  },

  store(data) {
    return api.post('/teams', data)
  },

  show(id) {
    return api.get(`/teams/${id}`).then((r) => r.data)
  },

  update(id, data) {
    return api.put(`/teams/${id}`, data)
  },

  destroy(id) {
    return api.delete(`/teams/${id}`)
  },

  members(id) {
    return api.get(`/teams/${id}/members`).then((r) => r.data)
  },

  addMembers(id, userIds) {
    return api.post(`/teams/${id}/members`, { user_ids: userIds })
  },

  removeMember(teamId, userId) {
    return api.delete(`/teams/${teamId}/members/${userId}`)
  },

  availableMembers(teamId = null) {
    const params = teamId ? { team_id: teamId } : {}
    return api.get('/teams/available-members', { params }).then((r) => r.data)
  },

  bulkDelete(ids) {
    return api.post('/teams/bulk-delete', { ids })
  },

  bulkStatusChange(ids, status) {
    return api.post('/teams/bulk-status', { ids, status })
  },
}
