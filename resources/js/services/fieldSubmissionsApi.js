import api from '@/lib/axios'

export default {
  getTeamOptions() {
    return api.get('/field-submissions/team-options')
  },
  store(data, submit = true) {
    return api.post('/field-submissions', { ...data, submit })
  },
}
