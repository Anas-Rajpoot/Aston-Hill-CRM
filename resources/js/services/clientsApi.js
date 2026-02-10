import api from '@/lib/axios'

export default {
  async index(params = {}) {
    const { data } = await api.get('/clients', { params })
    return data
  },

  async filters() {
    const { data } = await api.get('/clients/filters')
    return data
  },

  async columns() {
    const { data } = await api.get('/clients/columns')
    return data
  },

  async saveColumns(visibleColumns) {
    const { data } = await api.post('/clients/columns', {
      visible_columns: visibleColumns,
    })
    return data
  },

  async show(id) {
    const { data } = await api.get(`/clients/${id}`)
    return data
  },

  async update(id, payload) {
    const { data } = await api.put(`/clients/${id}`, payload)
    return data
  },

  async products(id, params = {}) {
    const { data } = await api.get(`/clients/${id}/products`, { params })
    return data
  },

  async vasRequests(id, params = {}) {
    const { data } = await api.get(`/clients/${id}/vas-requests`, { params })
    return data
  },

  async customerSupport(id, params = {}) {
    const { data } = await api.get(`/clients/${id}/customer-support`, { params })
    return data
  },

  async audits(id, params = {}) {
    const { data } = await api.get(`/clients/${id}/audits`, { params })
    return data
  },

  async updateCompanyDetails(id, payload) {
    const { data } = await api.put(`/clients/${id}/company-details`, payload)
    return data
  },

  async updateContacts(id, payload) {
    const { data } = await api.put(`/clients/${id}/contacts`, payload)
    return data
  },

  async updateAddresses(id, payload) {
    const { data } = await api.put(`/clients/${id}/addresses`, payload)
    return data
  },

  async alerts(id, params = {}) {
    const { data } = await api.get(`/clients/${id}/alerts`, { params })
    return data
  },
}
