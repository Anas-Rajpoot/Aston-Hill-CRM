<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import api, { invalidateCurrentDraftCache } from '@/services/leadSubmissionsApi'
import { useFormErrors } from '@/composables/useFormErrors'
import { formatTeamLabel } from '@/composables/useTeamLabel'
import { useSessionFormState } from '@/composables/useSessionFormState'
import { toDdMmYyyy } from '@/lib/dateFormat'
import { useAuthStore } from '@/stores/auth'
import { DOCUMENT_UPLOAD_EXTENSIONS } from '@/lib/documentUpload'

const EMIRATES_OPTIONS = [
  'Abu Dhabi',
  'Dubai',
  'Sharjah',
  'Ajman',
  'Umm Al Quwain',
  'Ras Al Khaimah',
  'Fujairah',
]

const props = defineProps({
  leadId: { type: Number, default: null },
  /** When true and leadId is null, do not load current draft (e.g. after "New lead submission"). */
  skipLoadDraft: { type: Boolean, default: false },
})
const router = useRouter()
const auth = useAuthStore()
const emit = defineEmits(['next', 'draft-saved'])

// Draft state
const draftId = ref(null)
const draftDate = ref(null)
const loadingDraft = ref(true)
const discarding = ref(false)

const form = ref({
  account_number: '',
  company_name: '',
  authorized_signatory_name: '',
  contact_number_gsm: '',
  alternate_contact_number: '',
  email: '',
  address: '',
  emirates: '',
  location_coordinates: '',
  product: '',
  offer: '',
  mrc_aed: '0',
  quantity: '1',
  ae_domain: '',
  gaid: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
  remarks: '',
})
const { restoreState, clearState } = useSessionFormState('submission.lead.step1', form)

const managers = ref([])
const teamLeaders = ref([])
const salesAgents = ref([])
const settingFromSalesAgent = ref(false)
/** When true, manager_id was set by TL or SA selection – don't clear TL/SA in manager watch */
const settingFromChild = ref(false)
const teamLabels = ref({
  manager: 'Manager Name',
  team_leader: 'Team Leader Name',
  sales_agent: 'Sales Agent Name',
})
const saving = ref(false)
const savingDraft = ref(false)
const categories = ref([])
const serviceTypes = ref([])
const selectedCategoryId = ref('')
const selectedTypeId = ref('')
const loadingServiceTypes = ref(false)

const primarySectionCollapsed = ref(false)
const serviceSectionCollapsed = ref(false)
const teamSectionCollapsed = ref(false)
const documentsSectionCollapsed = ref(false)

const docDefs = ref([])
const existingDocs = ref([])
const files = ref({})
const additionalDocs = ref([])
const MAX_FILE_MB = 3
const MAX_TOTAL_MB = 10
const ALLOWED_EXT = DOCUMENT_UPLOAD_EXTENSIONS
const DEFAULT_DOCUMENTS = [
  { key: 'trade_license', label: 'Trade License', required: true },
  { key: 'establishment_card', label: 'Establishment Card', required: false },
  { key: 'owner_emirates_id', label: 'Owner Emirates ID', required: false },
  { key: 'loa_poa', label: 'LOA / POA', required: false },
  { key: 'ejari', label: 'Ejari', required: false },
  { key: 'proposal_form', label: 'Proposal Form', required: false },
  { key: 'main_application', label: 'Main Application', required: false },
  { key: 'customer_confirmation_email', label: 'Customer Confirmation Email', required: false },
  { key: 'as_person_eid', label: 'AS Person EID', required: false },
  { key: 'rfs_marketing_approvals', label: 'RFS / Marketing / Migration Approvals', required: false },
  { key: 'fnp_binder', label: 'FNP Binder', required: false },
  { key: 'etisatis_bill', label: 'Etisatis Bill', required: false },
]
const MAX_ADDITIONAL_DOCS = 3
const canAddMoreAdditional = computed(() => additionalDocs.value.length < MAX_ADDITIONAL_DOCS)

const { errors, generalMessage, setErrors, clearErrors, clearFieldError, getError } = useFormErrors()

const uploadedDocCount = computed(() => {
  const schemaExisting = existingDocs.value.filter((d) => !String(d.doc_key || '').startsWith('additional_')).length
  const newSchema = Object.values(files.value).flat().filter(Boolean).length
  const additionalCount = additionalDocs.value.reduce((sum, ad) => {
    const existing = (ad.existingItems || []).length
    const newFiles = ad.files?.length || 0
    return sum + existing + newFiles
  }, 0)
  return schemaExisting + newSchema + additionalCount
})

const uploadedStatusText = computed(() => {
  const n = uploadedDocCount.value
  return n === 1 ? '1 document is uploaded' : `${n} documents are uploaded`
})

const totalSizeBytes = computed(() => {
  let total = existingDocs.value.reduce((s, d) => s + (d.size || 0), 0)
  Object.values(files.value).flat().forEach((f) => {
    if (f?.size) total += f.size
  })
  additionalDocs.value.forEach((ad) => {
    (ad.files || []).forEach((f) => {
      if (f?.size) total += f.size
    })
  })
  return total
})

const totalSizeMB = computed(() => (totalSizeBytes.value / (1024 * 1024)).toFixed(2))

const getDocFiles = (key) => files.value[key] || []
const getExistingForKey = (key) => existingDocs.value.filter((d) => d.doc_key === key)

function truncateFileName(name, maxLen = 28) {
  if (!name || typeof name !== 'string') return ''
  const t = name.trim()
  if (t.length <= maxLen) return t
  const ext = (t.match(/\.[a-zA-Z0-9]+$/)?.[0] || '').slice(0, 6)
  const base = t.slice(0, t.length - ext.length)
  if (base.length + ext.length <= maxLen) return t
  return base.slice(0, Math.max(0, maxLen - ext.length - 3)) + '...' + ext
}

const validateDocFile = (file) => {
  const ext = '.' + (file.name?.split('.').pop() || '').toLowerCase()
  if (!ALLOWED_EXT.includes(ext)) {
    return `File must be one of: ${ALLOWED_EXT.join(', ')}`
  }
  if (file.size > MAX_FILE_MB * 1024 * 1024) {
    return `File must not exceed ${MAX_FILE_MB}MB.`
  }
  return null
}

const onFileChange = (key, e) => {
  const selected = Array.from(e.target?.files || [])
  const validated = []
  const fileErr = {}
  selected.forEach((f) => {
    const err = validateDocFile(f)
    if (err) fileErr[f.name] = err
    else validated.push(f)
  })
  files.value[key] = validated
  clearFieldError(`documents.${key}`)
  clearFieldError('documents')
  if (Object.keys(fileErr).length) {
    const msg = Object.entries(fileErr).map(([n, m]) => `${n}: ${m}`).join('; ')
    setErrors({ response: { data: { errors: { [`documents.${key}`]: [msg] } } } })
  }
  e.target.value = ''
}

const addAdditionalDoc = () => {
  if (additionalDocs.value.length >= MAX_ADDITIONAL_DOCS) return
  additionalDocs.value.push({
    key: `additional_${Date.now()}`,
    label: `Additional Document ${additionalDocs.value.length + 1}`,
    files: [],
  })
}

const onAdditionalFileChange = (idx, e) => {
  const selected = Array.from(e.target?.files || [])
  const validated = []
  selected.forEach((f) => {
    const err = validateDocFile(f)
    if (!err) validated.push(f)
  })
  additionalDocs.value[idx].files = validated
  e.target.value = ''
}

const removeAdditionalDoc = (idx) => {
  additionalDocs.value.splice(idx, 1)
}

const buildDocumentsFormData = () => {
  const fd = new FormData()
  Object.entries(files.value).forEach(([key, arr]) => {
    ;(arr || []).forEach((f) => fd.append(`documents[${key}][]`, f))
  })
  additionalDocs.value.forEach((ad) => {
    ;(ad.files || []).forEach((f) => fd.append(`documents[${ad.key}][]`, f))
    if (ad.key) fd.append(`document_labels[${ad.key}]`, (ad.label || '').trim())
  })
  return fd
}

const validateDocumentsBeforeProceed = () => {
  const err = {}
  if (totalSizeBytes.value > MAX_TOTAL_MB * 1024 * 1024) {
    err.documents = [`Total upload size must not exceed ${MAX_TOTAL_MB}MB.`]
  }
  docDefs.value.filter((d) => d.required).forEach((d) => {
    const hasExisting = existingDocs.value.some((ed) => ed.doc_key === d.key)
    const hasNew = (files.value[d.key] || []).length > 0
    if (!hasExisting && !hasNew) {
      err[`documents.${d.key}`] = ['This document is required.']
    }
  })
  additionalDocs.value.forEach((ad) => {
    if ((ad.files || []).length > 0 && !(ad.label || '').trim()) {
      err[`additional_docs.${ad.key}`] = ['Title is required when a document is uploaded.']
    }
  })
  return Object.keys(err).length ? err : null
}

const hydrateDocumentsFromLead = (lead) => {
  const docs = Array.isArray(lead?.documents) ? lead.documents : []
  existingDocs.value = docs
  const additionalByKey = {}
  docs.forEach((d) => {
    const key = d.doc_key || ''
    if (key.startsWith('additional_')) {
      if (!additionalByKey[key]) {
        additionalByKey[key] = { key, label: d.label || 'Additional Document', files: [], existingItems: [] }
      }
      additionalByKey[key].existingItems.push({ id: d.id, original_name: d.original_name })
    }
  })
  additionalDocs.value = Object.values(additionalByKey)
}

const fetchTypeSchemaDocuments = async (typeId) => {
  if (!typeId) {
    initializeDocDefinitions(DEFAULT_DOCUMENTS)
    return
  }
  try {
    const schemaRes = await api.getTypeSchema(typeId)
    const schemaDocs = schemaRes?.data?.documents || []
    initializeDocDefinitions(schemaDocs.length > 0 ? schemaDocs : DEFAULT_DOCUMENTS)
  } catch {
    initializeDocDefinitions(DEFAULT_DOCUMENTS)
  }
}

const initializeDocDefinitions = (definitions = DEFAULT_DOCUMENTS) => {
  docDefs.value = (definitions || DEFAULT_DOCUMENTS).map((d) => ({ ...d, required: d.key === 'trade_license' }))
  docDefs.value.forEach((d) => {
    if (d.key && !(d.key in files.value)) files.value[d.key] = []
  })
}

const isResumingDraft = computed(() => !!draftId.value)
const currentSubmitterName = computed(() => auth.user?.name || '-')

// Format date for display (dd-mm-yyyy, time)
const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (Number.isNaN(d.getTime())) return ''
  const datePart = d.toISOString().slice(0, 10)
  const timePart = d.toTimeString().slice(0, 5)
  return `${toDdMmYyyy(datePart)} ${timePart}`.trim()
}

// Populate form from draft/lead data. Set skipTeamWatchers=true when loading from API so watchers don't clear team dropdowns.
const populateForm = (draft, skipTeamWatchers = false) => {
  if (skipTeamWatchers) {
    settingFromChild.value = true
    settingFromSalesAgent.value = true
  }
  form.value = {
    account_number: draft.account_number || '',
    company_name: draft.company_name || '',
    authorized_signatory_name: draft.authorized_signatory_name || '',
    contact_number_gsm: draft.contact_number_gsm || '',
    alternate_contact_number: draft.alternate_contact_number || '',
    email: draft.email || '',
    address: draft.address || '',
    emirates: draft.emirate || '',
    location_coordinates: draft.location_coordinates || '',
    product: draft.product || '',
    offer: draft.offer || '',
    mrc_aed: draft.mrc_aed != null ? String(Math.max(0, parseInt(draft.mrc_aed, 10) || 0)) : '0',
    quantity: draft.quantity ?? '1',
    ae_domain: draft.ae_domain || '',
    gaid: draft.gaid || '',
    manager_id: draft.manager_id != null ? String(draft.manager_id) : '',
    team_leader_id: draft.team_leader_id != null ? String(draft.team_leader_id) : '',
    sales_agent_id: draft.sales_agent_id != null ? String(draft.sales_agent_id) : '',
    remarks: draft.remarks || '',
  }
  if (skipTeamWatchers) {
    nextTick(() => {
      settingFromChild.value = false
      settingFromSalesAgent.value = false
    })
  }
}

// Reset form to empty state
const resetForm = () => {
  form.value = {
    account_number: '',
    company_name: '',
    authorized_signatory_name: '',
    contact_number_gsm: '',
    alternate_contact_number: '',
    email: '',
    address: '',
    emirates: '',
    location_coordinates: '',
    product: '',
    offer: '',
    mrc_aed: '0.00',
    quantity: '1',
    ae_domain: '',
    gaid: '',
    manager_id: '',
    team_leader_id: '',
    sales_agent_id: '',
    remarks: '',
  }
  draftId.value = null
  draftDate.value = null
  selectedCategoryId.value = ''
  selectedTypeId.value = ''
  serviceTypes.value = []
  existingDocs.value = []
  files.value = {}
  additionalDocs.value = []
  initializeDocDefinitions(DEFAULT_DOCUMENTS)
  clearErrors()
  clearState()
}

const fetchServiceTypes = async (categoryId) => {
  if (!categoryId) {
    serviceTypes.value = []
    return
  }
  loadingServiceTypes.value = true
  try {
    const { data } = await api.getServiceTypesByCategory(categoryId)
    serviceTypes.value = data || []
  } catch (_) {
    serviceTypes.value = []
  } finally {
    loadingServiceTypes.value = false
  }
}

const onCategoryChange = async () => {
  selectedTypeId.value = ''
  clearFieldError('service_category_id')
  clearFieldError('service_type_id')
  await fetchServiceTypes(selectedCategoryId.value)
  await fetchTypeSchemaDocuments('')
}

watch(selectedTypeId, async (id) => {
  await fetchTypeSchemaDocuments(id)
})

const pickFirstValue = (obj, keys) => {
  for (const key of keys) {
    const value = obj?.[key]
    if (value != null && value !== '') return String(value)
  }
  return ''
}

const resolveTeamLeaderManagerId = (teamLeader) => {
  const direct = pickFirstValue(teamLeader, ['manager_id', 'managerId'])
  if (direct) return direct
  const reportsTo = pickFirstValue(teamLeader, ['reports_to', 'reportsTo'])
  if (reportsTo && managers.value.some((m) => String(m.id) === reportsTo)) return reportsTo
  return ''
}

// Filter team leaders by manager; clear team_leader and sales_agent when manager changes
const filteredTeamLeaders = computed(() => {
  const mid = form.value.manager_id
  if (!mid) return teamLeaders.value
  return teamLeaders.value.filter((t) => resolveTeamLeaderManagerId(t) === String(mid))
})

// Filter sales agents by team leader
const filteredSalesAgents = computed(() => {
  const tlId = form.value.team_leader_id
  const managerId = form.value.manager_id
  const teamLeaderIdsUnderManager = new Set(
    teamLeaders.value
      .filter((t) => managerId && String(t.manager_id) === String(managerId))
      .map((t) => String(t.id))
  )

  const resolveSalesAgentTeamLeaderId = (sa) => {
    const direct = pickFirstValue(sa, ['team_leader_id', 'teamLeaderId'])
    const reportsTo = pickFirstValue(sa, ['reports_to', 'reportsTo'])
    if (direct) return direct
    if (reportsTo) {
      const asTeamLeader = teamLeaders.value.some((t) => String(t.id) === String(reportsTo))
      if (asTeamLeader) return String(reportsTo)
    }
    return ''
  }

  const resolveSalesAgentManagerId = (sa) => {
    const direct = pickFirstValue(sa, ['manager_id', 'managerId'])
    const reportsTo = pickFirstValue(sa, ['reports_to', 'reportsTo'])
    if (direct) return direct
    if (reportsTo) {
      const asManager = managers.value.some((m) => String(m.id) === String(reportsTo))
      if (asManager) return String(reportsTo)
    }
    const tlIdFromAgent = resolveSalesAgentTeamLeaderId(sa)
    if (tlIdFromAgent) {
      const tl = teamLeaders.value.find((t) => String(t.id) === String(tlIdFromAgent))
      const managerId = resolveTeamLeaderManagerId(tl)
      if (managerId) return managerId
    }
    return ''
  }

  if (tlId) {
    return salesAgents.value.filter((sa) => resolveSalesAgentTeamLeaderId(sa) === String(tlId))
  }
  if (managerId) {
    return salesAgents.value.filter((sa) => {
      const resolvedManagerId = resolveSalesAgentManagerId(sa)
      const resolvedTeamLeaderId = resolveSalesAgentTeamLeaderId(sa)
      return resolvedManagerId === String(managerId) || teamLeaderIdsUnderManager.has(resolvedTeamLeaderId)
    })
  }
  return salesAgents.value
})

const deriveSalesHierarchy = (salesAgent) => {
  let teamLeaderId = pickFirstValue(salesAgent, ['team_leader_id', 'teamLeaderId'])
  let managerId = pickFirstValue(salesAgent, ['manager_id', 'managerId'])
  const reportsTo = pickFirstValue(salesAgent, ['reports_to', 'reportsTo'])

  if (!teamLeaderId && reportsTo && teamLeaders.value.some((u) => String(u.id) === reportsTo)) {
    teamLeaderId = reportsTo
  }
  if (!managerId && reportsTo && managers.value.some((u) => String(u.id) === reportsTo)) {
    managerId = reportsTo
  }
  if (!managerId && teamLeaderId) {
    const tl = teamLeaders.value.find((u) => String(u.id) === String(teamLeaderId))
    managerId = resolveTeamLeaderManagerId(tl)
  }
  if (!teamLeaderId && managerId) {
    const tls = teamLeaders.value.filter((u) => resolveTeamLeaderManagerId(u) === String(managerId))
    if (tls.length === 1) teamLeaderId = String(tls[0].id)
  }

  return { teamLeaderId, managerId }
}

watch(
  () => form.value.manager_id,
  () => {
    if (settingFromChild.value) {
      nextTick(() => { settingFromChild.value = false })
      return
    }
    form.value.team_leader_id = ''
    form.value.sales_agent_id = ''
  }
)

watch(
  () => form.value.team_leader_id,
  (id) => {
    if (id) {
      const tl = teamLeaders.value.find((u) => String(u.id) === String(id))
      const managerId = resolveTeamLeaderManagerId(tl)
      if (managerId) {
        settingFromChild.value = true
        form.value.manager_id = String(managerId)
        nextTick(() => { settingFromChild.value = false })
      }
      if (!settingFromSalesAgent.value) form.value.sales_agent_id = ''
    } else if (!settingFromSalesAgent.value) {
      form.value.sales_agent_id = ''
    }
  }
)

// Auto-fill manager and team leader when sales agent is selected; clear parents when none selected
watch(
  () => form.value.sales_agent_id,
  (id) => {
    if (id) {
      const sa = salesAgents.value.find((u) => String(u.id) === String(id))
      if (sa) {
        const resolved = deriveSalesHierarchy(sa)
        settingFromSalesAgent.value = true
        settingFromChild.value = true
        if (resolved.teamLeaderId) form.value.team_leader_id = resolved.teamLeaderId
        if (resolved.managerId) form.value.manager_id = resolved.managerId
        nextTick(() => {
          settingFromSalesAgent.value = false
          settingFromChild.value = false
        })
      }
    }
  }
)

onMounted(async () => {
  try {
    await auth.fetchUser()
    if (!auth.isAuthenticated) {
      generalMessage.value = 'Your session expired. Please sign in again.'
      await router.push('/login')
      return
    }

    const [teamRes, catsRes] = await Promise.all([
      api.getTeamOptions(true),
      api.getCategories(),
    ])

    managers.value = teamRes.data.managers || []
    teamLeaders.value = teamRes.data.team_leaders || []
    salesAgents.value = teamRes.data.sales_agents || []
    categories.value = catsRes.data || []
    if (teamRes.data.labels) {
      teamLabels.value = { ...teamLabels.value, ...teamRes.data.labels }
    }

    // When wizard passes leadId, always use that lead so we never mix two different drafts (e.g. getCurrentDraft returning another id).
    if (props.leadId) {
      draftId.value = props.leadId
      try {
        const leadRes = await api.getLead(props.leadId)
        const lead = leadRes?.data
        if (lead) {
          draftDate.value = lead.updated_at
          populateForm(lead, true)
          hydrateDocumentsFromLead(lead)
          if (lead.service_category_id != null) {
            selectedCategoryId.value = String(lead.service_category_id)
            await fetchServiceTypes(selectedCategoryId.value)
            selectedTypeId.value = lead.service_type_id != null ? String(lead.service_type_id) : ''
            await fetchTypeSchemaDocuments(selectedTypeId.value)
          } else {
            await fetchTypeSchemaDocuments('')
          }
        }
      } catch (_) {
        // Keep draftId as props.leadId; form stays empty so user can fill and save
      }
    } else if (!props.skipLoadDraft) {
      const draftRes = await api.getCurrentDraft()
      if (draftRes.data.draft) {
        const draft = draftRes.data.draft
        draftId.value = draft.id
        draftDate.value = draft.updated_at
        populateForm(draft, true)
        hydrateDocumentsFromLead(draft)
        if (draft.service_category_id != null) {
          selectedCategoryId.value = String(draft.service_category_id)
          await fetchServiceTypes(selectedCategoryId.value)
          selectedTypeId.value = draft.service_type_id != null ? String(draft.service_type_id) : ''
          await fetchTypeSchemaDocuments(selectedTypeId.value)
        } else {
          await fetchTypeSchemaDocuments('')
        }
      }
    } else {
      await fetchTypeSchemaDocuments('')
    }
    // Restore unsaved typing after browser refresh.
    restoreState()
  } catch (_) {
    // Silent fail for team options
    initializeDocDefinitions(DEFAULT_DOCUMENTS)
  } finally {
    if (!docDefs.value.length) {
      initializeDocDefinitions(DEFAULT_DOCUMENTS)
    }
    loadingDraft.value = false
  }
})

const inputClass = (field) => {
  const hasError = errors.value && errors.value[field]
  return `w-full rounded-lg border px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary ${hasError ? 'border-red-500' : 'border-gray-300'}`
}

// Discard draft and start fresh
const discardAndStartFresh = async () => {
  if (!draftId.value) return
  
  discarding.value = true
  try {
    await api.discardDraft(draftId.value)
    invalidateCurrentDraftCache()
    resetForm()
  } catch (e) {
    setErrors(e)
  } finally {
    discarding.value = false
  }
}

// Save draft (create or update) – minimal validation
const saveDraft = async () => {
  clearErrors()
  if (!form.value.company_name?.trim()) {
    errors.value = { company_name: ['Company name is required.'] }
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  if (form.value.ae_domain?.trim()) {
    const aeResult = validateAeDomain(form.value.ae_domain)
    if (!aeResult.valid) {
      errors.value = { ae_domain: [aeResult.message] }
      generalMessage.value = 'Please correct the errors below.'
      return
    }
  }
  savingDraft.value = true
  try {
    let response
    if (draftId.value) {
      response = await api.updateStep1(draftId.value, form.value, true)
    } else {
      response = await api.storeStep1(form.value, true)
    }
    const data = response.data
    if (data?.id) {
      draftId.value = data.id
      draftDate.value = new Date().toISOString()
      if (selectedCategoryId.value && selectedTypeId.value) {
        await api.storeStep2(data.id, {
          service_category_id: Number(selectedCategoryId.value),
          service_type_id: Number(selectedTypeId.value),
        })
      }
      const draftDocErr = {}
      additionalDocs.value.forEach((ad) => {
        if ((ad.files || []).length > 0 && !(ad.label || '').trim()) {
          draftDocErr[`additional_docs.${ad.key}`] = ['Title is required when a document is uploaded.']
        }
      })
      if (!Object.keys(draftDocErr).length) {
        const fd = buildDocumentsFormData()
        fd.append('action', 'save')
        fd.append('step_after', '4')
        await api.storeStep3(data.id, fd)
        const leadRes = await api.getLead(data.id)
        const lead = leadRes?.data?.data ?? leadRes?.data
        hydrateDocumentsFromLead(lead || {})
        Object.keys(files.value).forEach((k) => { files.value[k] = [] })
      }
      invalidateCurrentDraftCache()
      clearState()
      emit('draft-saved', data.id)
    }
  } catch (e) {
    setErrors(e)
  } finally {
    savingDraft.value = false
  }
}

// .ae Domain validation (same rules as backend)
const AE_DOMAIN_FORBIDDEN_KEYWORDS = ['lac', 'rac', 'rat', 'sgns']
const AE_DOMAIN_SPECIAL = /[@#$%^&*()\-+={}\[\]:;'"\\<>,_?/!_`|\s]/
function validateAeDomain(value) {
  const v = (value || '').trim()
  // Optional field: empty value is valid.
  if (!v) return { valid: true, message: '' }
  if (/\s/.test(v)) return { valid: false, message: 'The domain must not contain spaces.' }
  if (/[0-9]/.test(v)) return { valid: false, message: 'The domain must not contain numbers (0–9).' }
  if (AE_DOMAIN_SPECIAL.test(v)) return { valid: false, message: 'The domain must not contain special characters such as: @ # $ % ^ & * ( ) - + = { } [ ] : ; \' " \\ <> , ? / ! _ ` |' }
  const lower = v.toLowerCase()
  for (const kw of AE_DOMAIN_FORBIDDEN_KEYWORDS) {
    if (lower.includes(kw)) return { valid: false, message: 'The domain must not contain these keywords (case-insensitive): LAC, RAC, RAT, SGNS.' }
  }
  if ((v.match(/\./g) || []).length !== 1) return { valid: false, message: 'The domain must contain only one dot (.).' }
  if (!lower.endsWith('.ae')) return { valid: false, message: 'The domain must end with .ae (example: example.ae).' }
  return { valid: true, message: 'You can use this Domain.' }
}

const aeDomainValidation = computed(() => validateAeDomain(form.value.ae_domain))

// Frontend validation for required fields (Step 1)
const validateStep1 = () => {
  const err = {}
  if (!form.value.company_name?.trim()) err.company_name = ['Company name is required.']
  if (!form.value.contact_number_gsm?.trim()) {
    err.contact_number_gsm = ['Contact number is required.']
  } else {
    const phoneErr = validatePhone(form.value.contact_number_gsm.trim())
    if (phoneErr) err.contact_number_gsm = [phoneErr]
  }
  if (form.value.alternate_contact_number?.trim()) {
    const altPhoneErr = validatePhone(form.value.alternate_contact_number.trim())
    if (altPhoneErr) err.alternate_contact_number = [altPhoneErr]
  }
  if (!form.value.address?.trim()) err.address = ['Complete address is required.']
  if (!form.value.emirates?.trim()) err.emirates = ['Emirates is required.']
  if (!form.value.product?.trim()) err.product = ['Product is required.']
  if (!selectedCategoryId.value) err.service_category_id = ['Please select a service category.']
  if (!selectedTypeId.value) err.service_type_id = ['Please select a service type.']
  if (form.value.ae_domain?.trim()) {
    const aeResult = validateAeDomain(form.value.ae_domain)
    if (!aeResult.valid) err.ae_domain = [aeResult.message]
  }
  if (!form.value.manager_id) err.manager_id = [`${formatTeamLabel(teamLabels.value.manager || 'manager')} is required.`]
  if (form.value.location_coordinates?.trim()) {
    const coordPattern = /^-?\d{1,3}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?$/
    const coords = form.value.location_coordinates.trim()
    if (!coordPattern.test(coords)) {
      err.location_coordinates = ['Enter valid coordinates (e.g. 25.2048, 55.2708).']
    } else {
      const [lat, lng] = coords.split(',').map(s => parseFloat(s.trim()))
      if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        err.location_coordinates = ['Latitude must be -90 to 90, longitude -180 to 180.']
      }
    }
  }
  if (form.value.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) err.email = ['Please enter a valid email address.']
  if (form.value.mrc_aed && (isNaN(parseInt(form.value.mrc_aed, 10)) || parseInt(form.value.mrc_aed, 10) < 0)) err.mrc_aed = ['MRC must be a valid whole number (0 or more).']
  if (form.value.quantity && (parseInt(form.value.quantity, 10) < 0 || !Number.isInteger(Number(form.value.quantity)))) err.quantity = ['Quantity must be a whole number.']
  return Object.keys(err).length ? err : null
}

const onPhoneInput = (field, e) => {
  form.value[field] = e.target.value.replace(/\D/g, '').slice(0, 12)
  clearFieldError(field)
}

function validatePhone(value) {
  if (!value) return null
  if (!/^\d{12}$/.test(value)) return 'Must be exactly 12 digits with no spaces (e.g. 971XXXXXXXXX).'
  if (!value.startsWith('971')) return 'Must start with 971.'
  return null
}

// MRC (AED) increment/decrement – start 0, step 1, up/down inside field
const mrcUp = () => {
  const v = Math.max(0, parseInt(form.value.mrc_aed, 10) || 0)
  form.value.mrc_aed = String(v + 1)
  clearFieldError('mrc_aed')
}
const mrcDown = () => {
  const v = Math.max(0, parseInt(form.value.mrc_aed, 10) || 0)
  form.value.mrc_aed = String(Math.max(0, v - 1))
  clearFieldError('mrc_aed')
}
const onMrcInput = (e) => {
  const v = e.target.value.replace(/\D/g, '')
  form.value.mrc_aed = v === '' ? '0' : String(parseInt(v, 10) || 0)
  clearFieldError('mrc_aed')
}
const onQuantityInput = (e) => {
  const v = e.target.value.replace(/\D/g, '')
  form.value.quantity = v === '' ? '' : String(parseInt(v, 10) || 0)
  clearFieldError('quantity')
}
const onGaidInput = (e) => {
  form.value.gaid = e.target.value
  clearFieldError('gaid')
}

// Submit and go to next step
const submit = async () => {
  clearErrors()
  const frontendErrors = validateStep1()
  if (frontendErrors) {
    errors.value = frontendErrors
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  const docErrors = validateDocumentsBeforeProceed()
  if (docErrors) {
    errors.value = { ...(errors.value || {}), ...docErrors }
    generalMessage.value = 'Please correct the errors below.'
    return
  }
  saving.value = true
  try {
    let response
    if (draftId.value) {
      response = await api.updateStep1(draftId.value, form.value)
    } else {
      response = await api.storeStep1(form.value)
    }
    const data = response.data
    if (data?.id) {
      await api.storeStep2(data.id, {
        service_category_id: Number(selectedCategoryId.value),
        service_type_id: Number(selectedTypeId.value),
      })
      const fd = buildDocumentsFormData()
      fd.append('action', 'save')
      fd.append('step_after', '4')
      await api.storeStep3(data.id, fd)
      const leadRes = await api.getLead(data.id)
      const lead = leadRes?.data?.data ?? leadRes?.data
      hydrateDocumentsFromLead(lead || {})
      Object.keys(files.value).forEach((k) => { files.value[k] = [] })
      invalidateCurrentDraftCache()
      clearState()
      emit('next', data.id)
    }
  } catch (e) {
    setErrors(e)
  } finally {
    saving.value = false
  }
}

const cancel = () => {
  clearState()
  router.push('/submissions')
}
</script>

<template>
  <!-- Loading state -->
  <div v-if="loadingDraft" class="flex items-center justify-center py-12">
    <svg class="animate-spin h-8 w-8 text-brand-primary" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <span class="ml-3 text-gray-600">Loading...</span>
  </div>

  <div v-else class="space-y-6">
    <!-- Resume Draft Banner -->
    <div v-if="isResumingDraft" class="rounded-lg bg-brand-primary-light border border-brand-primary-muted p-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <p class="text-sm font-medium text-brand-primary-hover">Resuming your draft</p>
          <p class="text-xs text-brand-primary">Last saved: {{ formatDate(draftDate) }}</p>
        </div>
      </div>
      <button
        type="button"
        @click="discardAndStartFresh"
        :disabled="discarding"
        class="text-sm text-brand-primary hover:text-brand-primary-hover underline disabled:opacity-50"
      >
        {{ discarding ? 'Discarding...' : 'Start Fresh' }}
      </button>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <!-- Validation errors summary -->
      <div v-if="generalMessage || Object.keys(errors).length" class="rounded-lg bg-red-50 border border-red-200 p-4">
        <p class="text-sm font-medium text-red-800">{{ generalMessage }}</p>
        <ul v-if="Object.keys(errors).length > 0" class="mt-2 text-sm text-red-700 list-disc list-inside space-y-0.5">
          <li v-for="(msgs, field) in errors" :key="field">{{ getError(field) }}</li>
        </ul>
      </div>

      <!-- Primary Information -->
      <div class="!mt-0">
        <button
          type="button"
          class="w-full flex items-center justify-between text-left text-base font-semibold text-gray-900 border-b border-gray-200 pb-2"
          :aria-expanded="(!primarySectionCollapsed).toString()"
          @click.prevent="primarySectionCollapsed = !primarySectionCollapsed"
        >
          <span>Primary Information</span>
          <div class="flex items-center gap-2 text-sm text-gray-500">
            <span>{{ primarySectionCollapsed ? 'Show' : 'Minimize' }}</span>
            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': primarySectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </button>

        <div v-show="!primarySectionCollapsed" class="mt-4 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
              <input v-model="form.account_number" type="text" placeholder="Enter account number" :class="inputClass('account_number')" @input="clearFieldError('account_number')" />
              <p v-if="getError('account_number')" class="mt-1 text-sm text-red-600">{{ getError('account_number') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Company Name as per Trade License <span class="text-red-500">*</span></label>
              <input v-model="form.company_name" type="text" placeholder="Enter company name" :class="inputClass('company_name')" />
              <p v-if="getError('company_name')" class="mt-1 text-sm text-red-600">{{ getError('company_name') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Authorized Signatory Name</label>
              <input v-model="form.authorized_signatory_name" type="text" placeholder="Enter signatory name" :class="inputClass('authorized_signatory_name')" @input="clearFieldError('authorized_signatory_name')" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
              <input v-model="form.contact_number_gsm" type="text" maxlength="12" placeholder="971XXXXXXXXX" :class="inputClass('contact_number_gsm')" @input="onPhoneInput('contact_number_gsm', $event)" />
              <p v-if="getError('contact_number_gsm')" class="mt-1 text-sm text-red-600">{{ getError('contact_number_gsm') }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Alternate Contact Number</label>
              <input v-model="form.alternate_contact_number" type="text" maxlength="12" placeholder="971XXXXXXXXX" :class="inputClass('alternate_contact_number')" @input="onPhoneInput('alternate_contact_number', $event)" />
              <p v-if="errors?.alternate_contact_number" class="mt-1 text-xs text-red-600">{{ errors.alternate_contact_number[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email ID</label>
              <input v-model="form.email" type="email" placeholder="email@example.com" :class="inputClass('email')" />
              <p v-if="getError('email')" class="mt-1 text-sm text-red-600">{{ getError('email') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">.ae Domain</label>
              <input v-model="form.ae_domain" type="text" placeholder="Enter Domain (e.g. example.ae)" :class="inputClass('ae_domain')" @input="clearFieldError('ae_domain')" />
              <p v-if="getError('ae_domain')" class="mt-1 text-sm text-red-600">{{ getError('ae_domain') }}</p>
              <p v-else-if="form.ae_domain?.trim() && aeDomainValidation.valid" class="mt-1 text-sm text-brand-primary">{{ aeDomainValidation.message }}</p>
              <p v-else-if="form.ae_domain?.trim() && !aeDomainValidation.valid" class="mt-1 text-sm text-red-600">{{ aeDomainValidation.message }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">GAID</label>
              <input :value="form.gaid" type="text" placeholder="Enter GAID" :class="inputClass('gaid')" @input="onGaidInput" />
              <p v-if="getError('gaid')" class="mt-1 text-sm text-red-600">{{ getError('gaid') }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Product <span class="text-red-500">*</span></label>
              <input v-model="form.product" type="text" placeholder="Search Product" :class="inputClass('product')" @input="clearFieldError('product')" />
              <p v-if="getError('product')" class="mt-1 text-sm text-red-600">{{ getError('product') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">MRC (AED)</label>
              <div class="flex w-full rounded-lg border overflow-hidden bg-white items-stretch" :class="getError('mrc_aed') ? 'border-red-500' : 'border-gray-300'">
                <input :value="form.mrc_aed" type="text" inputmode="numeric" placeholder="0" :class="['min-w-0 flex-1 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-primary border-0', getError('mrc_aed') ? 'border-red-500' : '']" @input="onMrcInput" />
                <div class="flex border-l border-gray-300 shrink-0 bg-gray-50">
                  <button type="button" @click="mrcUp" class="px-2 py-1 text-gray-600 hover:bg-gray-100 border-r border-gray-200 focus:outline-none flex items-center justify-center" aria-label="Increase MRC">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                  </button>
                  <button type="button" @click="mrcDown" class="px-2 py-1 text-gray-600 hover:bg-gray-100 focus:outline-none flex items-center justify-center" aria-label="Decrease MRC">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                  </button>
                </div>
              </div>
              <p v-if="getError('mrc_aed')" class="mt-1 text-sm text-red-600">{{ getError('mrc_aed') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
              <input :value="form.quantity" type="text" inputmode="numeric" placeholder="1" :class="inputClass('quantity')" @input="onQuantityInput" />
              <p v-if="getError('quantity')" class="mt-1 text-sm text-red-600">{{ getError('quantity') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Offer</label>
              <input v-model="form.offer" type="text" placeholder="Enter offer details" :class="inputClass('offer')" @input="clearFieldError('offer')" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="lg:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Complete Address as per Ejari <span class="text-red-500">*</span></label>
              <input v-model="form.address" type="text" placeholder="Enter complete address" :class="inputClass('address')" @input="clearFieldError('address')" />
              <p v-if="getError('address')" class="mt-1 text-sm text-red-600">{{ getError('address') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Emirates <span class="text-red-500">*</span></label>
              <select v-model="form.emirates" :class="inputClass('emirates')">
                <option value="">Select Emirates</option>
                <option v-for="e in EMIRATES_OPTIONS" :key="e" :value="e">{{ e }}</option>
              </select>
              <p v-if="getError('emirates')" class="mt-1 text-sm text-red-600">{{ getError('emirates') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Location Coordinates</label>
              <input v-model="form.location_coordinates" type="text" placeholder="e.g. 25.2048, 55.2708" :class="inputClass('location_coordinates')" @input="clearFieldError('location_coordinates')" />
              <p v-if="errors?.location_coordinates" class="mt-1 text-xs text-red-600">{{ errors.location_coordinates[0] }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Service Category & Type -->
      <div>
        <button
          type="button"
          class="w-full flex items-center justify-between text-left text-base font-semibold text-gray-900 border-b border-gray-200 pb-2"
          :aria-expanded="(!serviceSectionCollapsed).toString()"
          @click.prevent="serviceSectionCollapsed = !serviceSectionCollapsed"
        >
          <span>Service Details</span>
          <div class="flex items-center gap-2 text-sm text-gray-500">
            <span>{{ serviceSectionCollapsed ? 'Show' : 'Minimize' }}</span>
            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': serviceSectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </button>

        <div v-show="!serviceSectionCollapsed" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Service Categories <span class="text-red-500">*</span></label>
            <select v-model="selectedCategoryId" :class="inputClass('service_category_id')" @change="onCategoryChange">
              <option value="">Select Service Category</option>
              <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
            </select>
            <p v-if="getError('service_category_id')" class="mt-1 text-sm text-red-600">{{ getError('service_category_id') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Service Types <span class="text-red-500">*</span></label>
            <select v-model="selectedTypeId" :class="inputClass('service_type_id')" :disabled="!selectedCategoryId || loadingServiceTypes" @change="clearFieldError('service_type_id')">
              <option value="">{{ loadingServiceTypes ? 'Loading Service Types...' : 'Select Service Type' }}</option>
              <option v-for="type in serviceTypes" :key="type.id" :value="String(type.id)">{{ type.name }}</option>
            </select>
            <p v-if="getError('service_type_id')" class="mt-1 text-sm text-red-600">{{ getError('service_type_id') }}</p>
          </div>
        </div>
      </div>

      <!-- Team & Remarks -->
      <div>
        <button
          type="button"
          class="w-full flex items-center justify-between text-left text-base font-semibold text-gray-900 border-b border-gray-200 pb-2"
          :aria-expanded="(!teamSectionCollapsed).toString()"
          @click.prevent="teamSectionCollapsed = !teamSectionCollapsed"
        >
          <span>Team & Remarks</span>
          <div class="flex items-center gap-2 text-sm text-gray-500">
            <span>{{ teamSectionCollapsed ? 'Show' : 'Minimize' }}</span>
            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': teamSectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </button>

        <div v-show="!teamSectionCollapsed" class="mt-4 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Submitter Name</label>
              <input :value="currentSubmitterName" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-gray-100 text-gray-700" readonly />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ formatTeamLabel(teamLabels.manager || 'manager') }} Name <span class="text-red-500">*</span></label>
              <select v-model="form.manager_id" :class="inputClass('manager_id')" @change="clearFieldError('manager_id')">
                <option value="">Select {{ formatTeamLabel(teamLabels.manager || 'manager') }}</option>
                <option v-for="u in managers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
              </select>
              <p v-if="getError('manager_id')" class="mt-1 text-sm text-red-600">{{ getError('manager_id') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ formatTeamLabel(teamLabels.team_leader || 'team_leader') }} Name</label>
              <select v-model="form.team_leader_id" :class="inputClass('team_leader_id')" @change="clearFieldError('team_leader_id')">
                <option value="">Select {{ formatTeamLabel(teamLabels.team_leader || 'team_leader') }}</option>
                <option v-for="u in filteredTeamLeaders" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
              </select>
              <p v-if="getError('team_leader_id')" class="mt-1 text-sm text-red-600">{{ getError('team_leader_id') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ formatTeamLabel(teamLabels.sales_agent || 'sales_agent') }} Name</label>
              <select v-model="form.sales_agent_id" :class="inputClass('sales_agent_id')" @change="clearFieldError('sales_agent_id')">
                <option value="">Select {{ formatTeamLabel(teamLabels.sales_agent || 'sales_agent') }}</option>
                <option v-for="u in filteredSalesAgents" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
              </select>
              <p v-if="getError('sales_agent_id')" class="mt-1 text-sm text-red-600">{{ getError('sales_agent_id') }}</p>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Comment / Remarks</label>
            <textarea v-model="form.remarks" rows="2" placeholder="Enter any additional comments or remarks" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary" @input="clearFieldError('remarks')" />
            <p v-if="getError('remarks')" class="mt-1 text-sm text-red-600">{{ getError('remarks') }}</p>
          </div>
        </div>
      </div>

      <!-- Documents -->
      <div>
        <button
          type="button"
          class="w-full flex items-center justify-between text-left text-base font-semibold text-gray-900 border-b border-gray-200 pb-2"
          :aria-expanded="(!documentsSectionCollapsed).toString()"
          @click.prevent="documentsSectionCollapsed = !documentsSectionCollapsed"
        >
          <span>Documents</span>
          <div class="flex items-center gap-2 text-sm text-gray-500">
            <span>{{ documentsSectionCollapsed ? 'Show' : 'Minimize' }}</span>
            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': documentsSectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </button>

        <div v-show="!documentsSectionCollapsed" class="mt-4 space-y-4">
          <div>
            <p class="text-sm text-gray-600">{{ uploadedStatusText }}</p>
            <p class="mt-1 text-xs text-gray-500">
              Max {{ MAX_FILE_MB }} MB per file, {{ MAX_TOTAL_MB }} MB total. Allowed: {{ ALLOWED_EXT.join(', ') }}
            </p>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <div
                v-for="doc in docDefs"
                :key="doc.key"
                class="flex min-w-0 items-center gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm"
                :class="{ 'border-red-300': !!getError(`documents.${doc.key}`) }"
              >
                <div class="shrink-0 text-gray-500">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-gray-900">{{ doc.label }} <span v-if="doc.required" class="text-red-500">*</span></p>
                  <p v-if="getError(`documents.${doc.key}`)" class="mt-0.5 text-xs text-red-600">{{ getError(`documents.${doc.key}`) }}</p>
                  <template v-else>
                    <template v-for="(f, idx) in getDocFiles(doc.key)" :key="`new-${doc.key}-${idx}`">
                      <p class="mt-0.5 truncate text-xs text-gray-600" :title="f.name">{{ truncateFileName(f.name) }}</p>
                    </template>
                    <template v-for="ed in getExistingForKey(doc.key)" :key="`ex-${doc.key}-${ed.id}`">
                      <p class="mt-0.5 truncate text-xs text-gray-600" :title="ed.file_name || ed.label || ed.original_name">
                        {{ truncateFileName(ed.file_name || ed.label || ed.original_name || 'Uploaded') }}
                      </p>
                    </template>
                  </template>
                </div>
                <label class="shrink-0 cursor-pointer">
                  <input
                    type="file"
                    class="hidden"
                    :accept="ALLOWED_EXT.join(',')"
                    multiple
                    @change="onFileChange(doc.key, $event)"
                  />
                  <span class="inline-flex items-center gap-1 rounded-lg bg-brand-primary px-2 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-brand-primary-hover">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Upload
                  </span>
                </label>
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h4 class="text-sm font-semibold text-gray-900">Additional Documents</h4>
              <button
                type="button"
                @click="addAdditionalDoc"
                :disabled="!canAddMoreAdditional"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-primary hover:text-brand-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Document
              </button>
            </div>
            <p class="mt-2 text-xs text-gray-500">Total size: {{ totalSizeMB }} MB / {{ MAX_TOTAL_MB }} MB</p>

            <div v-if="additionalDocs.length" class="mt-4 space-y-3">
              <div
                v-for="(ad, idx) in additionalDocs"
                :key="ad.key"
                class="flex min-w-0 flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2.5 shadow-sm"
              >
                <div class="shrink-0 text-gray-400">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <input
                    v-model="ad.label"
                    type="text"
                    :placeholder="'Additional Document ' + (idx + 1)"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400 focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"
                    @input="clearFieldError(`additional_docs.${ad.key}`)"
                  />
                  <p v-if="getError(`additional_docs.${ad.key}`)" class="mt-0.5 text-xs text-red-600">{{ getError(`additional_docs.${ad.key}`) }}</p>
                  <template v-for="(f, fileIdx) in (ad.files || [])" :key="`ad-file-${ad.key}-${fileIdx}`">
                    <p class="mt-0.5 truncate text-xs text-gray-600" :title="f.name">{{ truncateFileName(f.name) }}</p>
                  </template>
                  <template v-for="item in (ad.existingItems || [])" :key="`ad-ex-${ad.key}-${item.id}`">
                    <p class="mt-0.5 truncate text-xs text-gray-600" :title="item.original_name">{{ truncateFileName(item.original_name) }}</p>
                  </template>
                </div>
                <label class="shrink-0 cursor-pointer">
                  <input
                    type="file"
                    class="hidden"
                    :accept="ALLOWED_EXT.join(',')"
                    multiple
                    @change="onAdditionalFileChange(idx, $event)"
                  />
                  <span class="inline-flex items-center gap-1.5 rounded-lg border border-brand-primary bg-brand-primary-light px-3 py-1.5 text-sm font-medium text-brand-primary hover:bg-brand-primary-light">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Upload
                  </span>
                </label>
                <button
                  type="button"
                  @click="removeAdditionalDoc(idx)"
                  class="shrink-0 text-sm text-red-600 hover:underline"
                >
                  Remove
                </button>
              </div>
            </div>
          </div>

          <p v-if="getError('documents')" class="text-red-600 text-sm">{{ getError('documents') }}</p>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-gray-200">
        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="cancel"
            class="px-4 py-2 rounded-lg bg-brand-primary text-white text-sm font-medium hover:bg-brand-primary-hover"
          >
            Cancel
          </button>
        </div>
        <div class="flex items-center gap-3">
          <button
            type="button"
            :disabled="savingDraft"
            @click="saveDraft"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200 disabled:opacity-50"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            {{ savingDraft ? 'Saving...' : 'Save as Draft' }}
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium disabled:opacity-50 bg-brand-primary hover:bg-brand-primary-hover"
          >
            Next
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
