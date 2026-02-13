import api from '@/lib/axios'

export default {
  index(params = {}) {
    return api.get('/dsp-tracker', { params })
  },

  import(rows) {
    return api.post('/dsp-tracker/import', { rows })
  },

  deleteBatch(batchId) {
    return api.delete(`/dsp-tracker/batch/${batchId}`)
  },
}
