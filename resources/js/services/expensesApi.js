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
    return api.post('/expenses', payload)
  },

  show(id) {
    return api.get(`/expenses/${id}`)
  },
}
