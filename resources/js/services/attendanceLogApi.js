import api from '@/lib/axios'

export default {
  index(params = {}) {
    return api.get('/attendance-log', { params })
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
