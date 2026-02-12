import api from '@/lib/axios'

export default {
  index(params = {}) {
    return api.get('/attendance-log', { params })
  },

  summary() {
    return api.get('/attendance-log/summary')
  },

  filters() {
    return api.get('/attendance-log/filters')
  },

  forceLogoutLog(logId) {
    return api.post(`/attendance-log/force-logout/log/${logId}`)
  },

  forceLogoutUser(userId) {
    return api.post(`/attendance-log/force-logout/user/${userId}`)
  },
}
