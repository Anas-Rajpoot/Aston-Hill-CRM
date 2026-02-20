import api from '@/lib/axios'

/** In-memory cache for getCurrentDraft (1 min TTL). Cleared by invalidateCurrentDraftCache. */
let currentDraftCache = null
let currentDraftCacheAt = 0
const CURRENT_DRAFT_TTL_MS = 60 * 1000

/** In-memory cache for getTeamOptions (2 min TTL). */
let teamOptionsCache = null
let teamOptionsCacheAt = 0
const TEAM_OPTIONS_TTL_MS = 2 * 60 * 1000

export function invalidateCurrentDraftCache() {
  currentDraftCache = null
  currentDraftCacheAt = 0
}

const leadSubmissionsApi = {
  // ─── Wizard (step1–4) ─────────────────────────────────────────────────────
  getTeamOptions() {
    if (teamOptionsCache && Date.now() - teamOptionsCacheAt < TEAM_OPTIONS_TTL_MS) {
      return Promise.resolve(teamOptionsCache)
    }
    const p = api.get('/field-submissions/team-options')
    p.then((res) => {
      teamOptionsCache = res
      teamOptionsCacheAt = Date.now()
    })
    return p
  },

  getCurrentDraft() {
    if (currentDraftCache && Date.now() - currentDraftCacheAt < CURRENT_DRAFT_TTL_MS) {
      return Promise.resolve(currentDraftCache)
    }
    const p = api.get('/lead-submissions/current-draft')
    p.then((res) => {
      currentDraftCache = res
      currentDraftCacheAt = Date.now()
    })
    return p
  },

  getLead(id) {
    return api.get(`/lead-submissions/${id}`)
  },

  /** Resubmission form: load lead + categories + document definitions (rejected only). */
  getResubmissionData(id) {
    return api.get(`/lead-submissions/${id}/resubmission-data`)
  },

  /** Resubmit: submit form (action: 'draft' | 'submit') and optional document files. */
  resubmit(id, payload, files = null) {
    const form = new FormData()
    Object.entries(payload).forEach(([k, v]) => {
      if (v != null && v !== '') form.append(k, v)
    })
    if (files) {
      Object.entries(files).forEach(([key, fileList]) => {
        const list = Array.isArray(fileList) ? fileList : [fileList]
        list.forEach((f) => f && form.append(`documents[${key}][]`, f))
      })
    }
    return api.post(`/lead-submissions/${id}/resubmit`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  discardDraft(id) {
    return api.delete(`/lead-submissions/${id}/discard`)
  },

  storeStep1(data, asDraft = false) {
    return api.post('/lead-submissions/step-1', { ...data, draft: !!asDraft })
  },

  updateStep1(id, data, asDraft = false) {
    return api.put(`/lead-submissions/${id}/step-1`, { ...data, draft: !!asDraft })
  },

  getCategories() {
    return api.get('/lead-submissions/categories')
  },

  getServiceTypesByCategory(categoryId) {
    return api.get('/lead-submissions/service-types', {
      params: { service_category_id: categoryId },
    })
  },

  storeStep2(leadId, data) {
    return api.post(`/lead-submissions/${leadId}/step-2`, data)
  },

  getTypeSchema(typeId) {
    return api.get(`/lead-submissions/type-schema/${typeId}`)
  },

  storeStep3(leadId, formData) {
    return api.post(`/lead-submissions/${leadId}/step-3`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  submit(leadId) {
    return api.post(`/lead-submissions/${leadId}/submit`)
  },

  // ─── Listing (optimized, < 1s) ───────────────────────────────────────────
  async index(params = {}, options = {}) {
    const { data } = await api.get('/lead-submissions', { params, signal: options.signal })
    return data
  },

  async filters() {
    const { data } = await api.get('/lead-submissions/filters')
    return data
  },

  async columns() {
    const { data } = await api.get('/lead-submissions/columns')
    return data
  },

  async saveColumns(visibleColumns) {
    const { data } = await api.post('/lead-submissions/columns', {
      visible_columns: visibleColumns,
    })
    return data
  },

  async updateStatus(leadId, status) {
    const { data } = await api.patch(`/lead-submissions/${leadId}/status`, { status })
    return data
  },

  async updateStatusChangedAt(leadId, statusChangedAt) {
    const { data } = await api.patch(`/lead-submissions/${leadId}/status-changed-at`, {
      status_changed_at: statusChangedAt,
    })
    return data
  },

  /** Back office edit (superadmin / backoffice only) */
  async getBackOfficeOptions() {
    const { data } = await api.get('/lead-submissions/back-office-options')
    return data
  },

  async updateBackOffice(leadId, data) {
    const { data: res } = await api.put(`/lead-submissions/${leadId}/back-office`, data)
    return res
  },

  /** Bulk assign: dispatches queue job, returns tracking_id for progress polling. */
  async bulkAssign(leadIds, data) {
    const { data: res } = await api.post('/lead-submissions/bulk-assign', {
      lead_ids: leadIds,
      executive_id: data.executive_id,
    })
    return res
  },

  async bulkAssignStatus(trackingId) {
    const { data } = await api.get(`/lead-submissions/bulk-assign/${trackingId}/status`)
    return data
  },

  /** Change history (audit log) for a single lead submission. */
  async getAudits(leadId) {
    const { data } = await api.get(`/lead-submissions/${leadId}/audits`)
    return data
  },

  /** Global audit log (all lead submission changes). Super admin only. Params: page, per_page, lead_submission_id. */
  async getAuditLog(params = {}) {
    const { data } = await api.get('/lead-submissions/audit-log', { params })
    return data
  },

  /**
   * Download a single document. Returns blob; caller should trigger save (e.g. create object URL and click).
   */
  async downloadDocument(leadId, documentId) {
    const { data } = await api.get(
      `/lead-submissions/${leadId}/documents/${documentId}/download`,
      { responseType: 'blob' }
    )
    return data
  },

  /**
   * Bulk download all documents as zip. Returns blob.
   */
  async bulkDownloadDocuments(leadId) {
    const { data } = await api.get(
      `/lead-submissions/${leadId}/documents/bulk-download`,
      { responseType: 'blob' }
    )
    return data
  },

  /** Remove a single document (superadmin / back_office only). */
  async deleteDocument(leadId, documentId) {
    const { data } = await api.delete(`/lead-submissions/${leadId}/documents/${documentId}`)
    return data
  },

  /** Add documents (FormData with documents[]). Returns { message }. */
  async uploadDocuments(leadId, formData) {
    const { data } = await api.post(`/lead-submissions/${leadId}/documents`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },
}

export default leadSubmissionsApi
