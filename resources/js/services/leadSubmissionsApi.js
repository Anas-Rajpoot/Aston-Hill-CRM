import axios from '@/lib/axios'

export default {
  storeStep1(data) {
    return axios.post('/lead-submissions/step-1', data)
  },
  storeStep2(id, data) {
    return axios.post(`/lead-submissions/${id}/step-2`, data)
  },
  storeStep3(id, data) {
    return axios.post(`/lead-submissions/${id}/step-3`, data)
  },
  storeStep4(id, data) {
    return axios.post(`/lead-submissions/${id}/step-4`, data)
  },
  submit(id) {
    return axios.post(`/lead-submissions/${id}/submit`)
  },
}
