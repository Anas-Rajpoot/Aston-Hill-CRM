import api from '@/lib/axios'

/** In-memory cache for getCurrentDraft (1 min TTL). Cleared by invalidateCurrentDraftCache. */
let currentDraftCache = null
let currentDraftCacheAt = 0
const CURRENT_DRAFT_TTL_MS = 60 * 1000

/** In-memory cache for getTeamOptions (2 min TTL). */
let teamOptionsCache = null
let teamOptionsCacheAt = 0
const TEAM_OPTIONS_TTL_MS = 2 * 60 * 1000

/** In-memory cache for getBackOfficeOptions (5 min TTL). */
let backOfficeOptionsCache = null
let backOfficeOptionsCacheAt = 0
const BACK_OFFICE_OPTIONS_TTL_MS = 5 * 60 * 1000

const NO_AUTH_REDIRECT = { skipAuthRedirect: true, withCredentials: true }

export function invalidateCurrentDraftCache() {
  currentDraftCache = null
  currentDraftCacheAt = 0
}

export function invalidateTeamOptionsCache() {
  teamOptionsCache = null
  teamOptionsCacheAt = 0
}

const leadSubmissionsApi = {
  // ─── Wizard (step1–4) ─────────────────────────────────────────────────────
  getTeamOptions(forceRefresh = false) {
    if (!forceRefresh && teamOptionsCache && Date.now() - teamOptionsCacheAt < TEAM_OPTIONS_TTL_MS) {
      return Promise.resolve(teamOptionsCache)
    }
    return api.get('/field-submissions/team-options', {
      ...NO_AUTH_REDIRECT,
      params: forceRefresh ? { fresh: 1 } : undefined,
    }).then((res) => {
      teamOptionsCache = res
      teamOptionsCacheAt = Date.now()
      return res
    })
  },

  getCurrentDraft() {
    if (currentDraftCache && Date.now() - currentDraftCacheAt < CURRENT_DRAFT_TTL_MS) {
      return Promise.resolve(currentDraftCache)
    }
    return api.get('/lead-submissions/current-draft', NO_AUTH_REDIRECT).then((res) => {
      currentDraftCache = res
      currentDraftCacheAt = Date.now()
      return res
    })
  },

  getLead(id) {
    return api.get(`/lead-submissions/${id}`, NO_AUTH_REDIRECT)
  },

  /** Resubmission form: load lead + categories + document definitions (rejected only). */
  getResubmissionData(id) {
    return api.get(`/lead-submissions/${id}/resubmission-data`, NO_AUTH_REDIRECT)
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
      skipAuthRedirect: true,
    })
  },

  discardDraft(id) {
    return api.delete(`/lead-submissions/${id}/discard`, NO_AUTH_REDIRECT)
  },

  storeStep1(data, asDraft = false) {
    return api.post('/lead-submissions/step-1', { ...data, draft: !!asDraft }, NO_AUTH_REDIRECT)
  },

  updateStep1(id, data, asDraft = false) {
    return api.put(`/lead-submissions/${id}/step-1`, { ...data, draft: !!asDraft }, NO_AUTH_REDIRECT)
  },

  getCategories() {
    return api.get('/lead-submissions/categories', NO_AUTH_REDIRECT)
  },

  getServiceTypesByCategory(categoryId) {
    return api.get('/lead-submissions/service-types', {
      params: { service_category_id: categoryId },
      skipAuthRedirect: true,
    })
  },

  storeStep2(leadId, data) {
    return api.post(`/lead-submissions/${leadId}/step-2`, data, NO_AUTH_REDIRECT)
  },

  getTypeSchema(typeId) {
    return api.get(`/lead-submissions/type-schema/${typeId}`, NO_AUTH_REDIRECT)
  },

  storeStep3(leadId, formData) {
    return api.post(`/lead-submissions/${leadId}/step-3`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      skipAuthRedirect: true,
    })
  },

  submit(leadId) {
    return api.post(`/lead-submissions/${leadId}/submit`, {}, NO_AUTH_REDIRECT)
  },

  // ─── Listing (optimized, < 1s) ───────────────────────────────────────────
  async index(params = {}, options = {}) {
    const { data } = await api.get('/lead-submissions', { params, signal: options.signal, skipAuthRedirect: true })
    return data
  },

  async filters() {
    const { data } = await api.get('/lead-submissions/filters', NO_AUTH_REDIRECT)
    return data
  },

  async columns() {
    const { data } = await api.get('/lead-submissions/columns', NO_AUTH_REDIRECT)
    return data
  },

  async saveColumns(visibleColumns) {
    const { data } = await api.post('/lead-submissions/columns', {
      visible_columns: visibleColumns,
    }, NO_AUTH_REDIRECT)
    return data
  },

  async updateStatus(leadId, status) {
    const { data } = await api.patch(`/lead-submissions/${leadId}/status`, { status }, NO_AUTH_REDIRECT)
    return data
  },

  async updateStatusChangedAt(leadId, statusChangedAt) {
    const { data } = await api.patch(`/lead-submissions/${leadId}/status-changed-at`, {
      status_changed_at: statusChangedAt,
    }, NO_AUTH_REDIRECT)
    return data
  },

  /** Back office edit (superadmin / backoffice only) */
  async getBackOfficeOptions(forceRefresh = false) {
    if (!forceRefresh && backOfficeOptionsCache && Date.now() - backOfficeOptionsCacheAt < BACK_OFFICE_OPTIONS_TTL_MS) {
      return backOfficeOptionsCache
    }
    const { data } = await api.get('/lead-submissions/back-office-options', NO_AUTH_REDIRECT)
    backOfficeOptionsCache = data
    backOfficeOptionsCacheAt = Date.now()
    return data
  },

  async updateBackOffice(leadId, data) {
    const { data: res } = await api.put(`/lead-submissions/${leadId}/back-office`, data, NO_AUTH_REDIRECT)
    return res
  },

  async destroy(leadId) {
    const { data } = await api.delete(`/lead-submissions/${leadId}`, NO_AUTH_REDIRECT)
    return data
  },

  /** Bulk assign: dispatches queue job, returns tracking_id for progress polling. */
  async bulkAssign(leadIds, data) {
    const { data: res } = await api.post('/lead-submissions/bulk-assign', {
      lead_ids: leadIds,
      executive_id: data.executive_id,
    }, NO_AUTH_REDIRECT)
    return res
  },

  async bulkAssignStatus(trackingId) {
    const { data } = await api.get(`/lead-submissions/bulk-assign/${trackingId}/status`, NO_AUTH_REDIRECT)
    return data
  },

  /** Change history (audit log) for a single lead submission. */
  async getAudits(leadId) {
    const { data } = await api.get(`/lead-submissions/${leadId}/audits`, NO_AUTH_REDIRECT)
    return data
  },

  /** Global audit log (all lead submission changes). Super admin only. Params: page, per_page, lead_submission_id. */
  async getAuditLog(params = {}) {
    const { data } = await api.get('/lead-submissions/audit-log', { params, skipAuthRedirect: true })
    return data
  },

  /**
   * Download a single document. Returns blob; caller should trigger save (e.g. create object URL and click).
   */
  async downloadDocument(leadId, documentId) {
    const { data } = await api.get(
      `/lead-submissions/${leadId}/documents/${documentId}/download`,
      { responseType: 'blob', skipAuthRedirect: true }
    )
    return data
  },

  /**
   * Bulk download all documents as zip. Returns blob.
   */
  async bulkDownloadDocuments(leadId) {
    const { data } = await api.get(
      `/lead-submissions/${leadId}/documents/bulk-download`,
      { responseType: 'blob', skipAuthRedirect: true }
    )
    return data
  },

  /** Remove a single document (superadmin / back_office only). */
  async deleteDocument(leadId, documentId) {
    const { data } = await api.delete(`/lead-submissions/${leadId}/documents/${documentId}`, NO_AUTH_REDIRECT)
    return data
  },

  /** Add documents (FormData with documents[]). Returns { message }. */
  async uploadDocuments(leadId, formData) {
    const { data } = await api.post(`/lead-submissions/${leadId}/documents`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      skipAuthRedirect: true,
    })
    return data
  },
}

export default leadSubmissionsApi
