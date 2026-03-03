/**
 * Date display format: dd-mm-yyyy (API still uses yyyy-mm-dd).
 */

/**
 * Convert yyyy-mm-dd or ISO date string to dd-mm-yyyy for display.
 * @param {string} ymd - Date in yyyy-mm-dd, or ISO (e.g. 2024-01-15T00:00:00.000Z), or empty
 * @returns {string} dd-mm-yyyy or ''
 */
export function toDdMmYyyy(ymd) {
  if (!ymd || typeof ymd !== 'string') return ''
  const s = ymd.trim()
  const ymdOnly = s.slice(0, 10)
  const parts = ymdOnly.split('-')
  if (parts.length !== 3) return ''
  const [y, m, d] = parts
  if (y.length === 4 && m.length <= 2 && d.length <= 2) {
    return `${d.padStart(2, '0')}-${m.padStart(2, '0')}-${y}`
  }
  return ''
}

/**
 * Format any date value for display as dd-mm-yyyy (handles Date, ISO string, yyyy-mm-dd).
 * @param {string|Date} date - Date value
 * @returns {string} dd-mm-yyyy or '' or '—' for invalid
 */
export function formatDateDdMmYyyy(date) {
  if (date == null) return '—'
  if (date instanceof Date) {
    if (Number.isNaN(date.getTime())) return '—'
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${d}-${m}-${y}`
  }
  const str = typeof date === 'string' ? date.trim().slice(0, 10) : ''
  if (!str) return '—'
  const out = toDdMmYyyy(str)
  return out || '—'
}

/**
 * Parse dd-mm-yyyy or yyyy-mm-dd to yyyy-mm-dd for API.
 * @param {string} str - User input (dd-mm-yyyy or partial)
 * @returns {string} yyyy-mm-dd or '' if invalid
 */
export function fromDdMmYyyy(str) {
  if (!str || typeof str !== 'string') return ''
  const trimmed = str.trim()
  if (!trimmed) return ''
  const parts = trimmed.split(/[-/]/)
  if (parts.length !== 3) return ''
  const [a, b, c] = parts
  let d, m, y
  if (a.length === 4) {
    y = a
    m = b
    d = c
  } else if (c.length === 4) {
    d = a
    m = b
    y = c
  } else {
    return ''
  }
  let yInt = parseInt(y, 10)
  const mInt = parseInt(m, 10)
  const dInt = parseInt(d, 10)
  if (Number.isNaN(yInt) || Number.isNaN(mInt) || Number.isNaN(dInt)) return ''
  if (yInt < 100) yInt += 2000
  const date = new Date(yInt, mInt - 1, dInt)
  if (Number.isNaN(date.getTime())) return ''
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const MONTHS_3 = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

function parseDateValue(value) {
  if (value == null || value === '') return null
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value
  }
  const raw = String(value).trim()
  if (!raw) return null

  const isoLike = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/)
  if (isoLike) {
    const year = Number(isoLike[1])
    const month = Number(isoLike[2]) - 1
    const day = Number(isoLike[3])
    const hour = Number(isoLike[4] ?? 0)
    const minute = Number(isoLike[5] ?? 0)
    const second = Number(isoLike[6] ?? 0)
    const localDate = new Date(year, month, day, hour, minute, second)
    return Number.isNaN(localDate.getTime()) ? null : localDate
  }

  const parsed = new Date(raw)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

/**
 * CRM standard for user-entered date fields: DD-MMM-YYYY
 */
export function formatUserDate(value, fallback = '—') {
  const date = parseDateValue(value)
  if (!date) return fallback
  const day = String(date.getDate()).padStart(2, '0')
  const mon = MONTHS_3[date.getMonth()]
  const year = date.getFullYear()
  return `${day}-${mon}-${year}`
}

/**
 * CRM standard for system-generated datetime fields: DD-MMM-YYYY HH:mm:ss
 */
export function formatSystemDateTime(value, fallback = '—') {
  const date = parseDateValue(value)
  if (!date) return fallback
  const day = String(date.getDate()).padStart(2, '0')
  const mon = MONTHS_3[date.getMonth()]
  const year = date.getFullYear()
  const hh = String(date.getHours()).padStart(2, '0')
  const mm = String(date.getMinutes()).padStart(2, '0')
  const ss = String(date.getSeconds()).padStart(2, '0')
  return `${day}-${mon}-${year} ${hh}:${mm}:${ss}`
}

/**
 * Format date for display: dd Mon yyyy (e.g. 15 Jan 2024, 10 Mar 2024).
 * @param {string} ymd - Date in yyyy-mm-dd or empty
 * @returns {string} dd Mon yyyy or ''
 */
export function toDdMonYyyy(ymd) {
  if (!ymd || typeof ymd !== 'string') return ''
  const parts = ymd.trim().split('-')
  if (parts.length !== 3) return ''
  const [y, m, d] = parts
  const mInt = parseInt(m, 10)
  const dInt = parseInt(d, 10)
  if (Number.isNaN(mInt) || Number.isNaN(dInt) || mInt < 1 || mInt > 12) return ''
  const monthLabel = MONTHS_3[mInt - 1]
  return `${String(dInt).padStart(2, '0')} ${monthLabel} ${y}`
}

/**
 * Format date for display: dd-Mon-yyyy (e.g. 15-Jan-2024, 10-Mar-2024).
 * @param {string} ymd - Date in yyyy-mm-dd or empty
 * @returns {string} dd-Mon-yyyy or ''
 */
export function toDdMonYyyyDash(ymd) {
  if (!ymd || typeof ymd !== 'string') return ''
  const parts = ymd.trim().slice(0, 10).split('-')
  if (parts.length !== 3) return ''
  const [y, m, d] = parts
  const mInt = parseInt(m, 10)
  const dInt = parseInt(d, 10)
  if (Number.isNaN(mInt) || Number.isNaN(dInt) || mInt < 1 || mInt > 12) return ''
  return `${String(dInt).padStart(2, '0')}-${MONTHS_3[mInt - 1]}-${y}`
}

const MONTHS_3_LOWER = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']

/**
 * Format date for display: dd-mon-yyyy (e.g. 02-jan-2026).
 * @param {string} ymd - Date in yyyy-mm-dd or empty
 * @returns {string} dd-mon-yyyy or ''
 */
export function toDdMonYyyyLower(ymd) {
  if (!ymd || typeof ymd !== 'string') return ''
  const parts = ymd.trim().split('-')
  if (parts.length !== 3) return ''
  const [y, m, d] = parts
  const mInt = parseInt(m, 10)
  const dInt = parseInt(d, 10)
  if (Number.isNaN(mInt) || Number.isNaN(dInt) || mInt < 1 || mInt > 12) return ''
  const monthLabel = MONTHS_3[mInt - 1]
  return `${String(dInt).padStart(2, '0')}-${monthLabel}-${y}`
}

/**
 * Parse dd-mon-yyyy (e.g. 02-jan-2026) to yyyy-mm-dd for API.
 * @param {string} str - User input
 * @returns {string} yyyy-mm-dd or ''
 */
export function fromDdMonYyyyLower(str) {
  if (!str || typeof str !== 'string') return ''
  const trimmed = str.trim().toLowerCase()
  if (!trimmed) return ''
  const parts = trimmed.split(/[-/]/)
  if (parts.length !== 3) return ''
  const [a, b, c] = parts
  const monthIndex = MONTHS_3_LOWER.indexOf(b)
  if (monthIndex === -1) return ''
  const dInt = parseInt(a, 10)
  const yInt = parseInt(c, 10)
  if (Number.isNaN(dInt) || Number.isNaN(yInt)) return ''
  const year = yInt < 100 ? yInt + 2000 : yInt
  const date = new Date(year, monthIndex, dInt)
  if (Number.isNaN(date.getTime()) || date.getMonth() !== monthIndex) return ''
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}
