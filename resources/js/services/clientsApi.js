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

  async importCsv(file) {
    const formData = new FormData()
    formData.append('file', file)
    const { data } = await api.post('/clients/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },

  async store(payload) {
    const { data } = await api.post('/clients', payload)
    return data
  },

  async storeProduct(id, payload) {
    const { data } = await api.post(`/clients/${id}/products`, payload)
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

  async inlineUpdate(id, payload) {
    const { data } = await api.put(`/clients/${id}/inline`, payload)
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

  async storeAlert(id, payload) {
    const { data } = await api.post(`/clients/${id}/alerts`, payload)
    return data
  },

  async updateAlert(clientId, alertId, payload) {
    const { data } = await api.put(`/clients/${clientId}/alerts/${alertId}`, payload)
    return data
  },

  async resolveAlert(clientId, alertId) {
    const { data } = await api.post(`/clients/${clientId}/alerts/${alertId}/resolve`)
    return data
  },

  async generateRenewalAlerts(params = {}) {
    const { data } = await api.post('/clients/renewal-alerts/generate', params)
    return data
  },

  async bulkAssignCsr(ids, csrName) {
    const { data } = await api.post('/clients/bulk-assign-csr', { ids, csr_name: csrName })
    return data
  },

  async bulkAssignAccountManager(ids, accountManagerName) {
    const { data } = await api.post('/clients/bulk-assign-account-manager', { ids, account_manager_name: accountManagerName })
    return data
  },

  async bulkDelete(ids) {
    const { data } = await api.post('/clients/bulk-delete', { ids })
    return data
  },
}
