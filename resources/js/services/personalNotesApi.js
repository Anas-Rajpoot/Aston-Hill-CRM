import api from '@/lib/axios'

export default {
  async index() {
    const { data } = await api.get('/personal-notes')
    return data
  },

  async show(id) {
    const { data } = await api.get(`/personal-notes/${id}`)
    return data
  },

  async create(payload) {
    const { data } = await api.post('/personal-notes', payload)
    return data
  },

  async update(id, payload) {
    const { data } = await api.put(`/personal-notes/${id}`, payload)
    return data
  },

  async delete(id) {
    await api.delete(`/personal-notes/${id}`)
  },
}
