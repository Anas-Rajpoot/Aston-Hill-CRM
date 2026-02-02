import api from '@/lib/axios'

export default {
  index(params = {}) {
    return api.get('/users', { params })
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
