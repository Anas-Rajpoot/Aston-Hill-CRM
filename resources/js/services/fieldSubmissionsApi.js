import axios from '@/lib/axios'

export default {
  getTeamOptions() {
    return axios.get('/field-submissions/team-options')
  },
  store(data, submit = true) {
    return axios.post('/field-submissions', { ...data, submit })
  },
}
