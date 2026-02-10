/**
 * Date display format: dd-mm-yyyy (API still uses yyyy-mm-dd).
 */

/**
 * Convert yyyy-mm-dd to dd-mm-yyyy for display.
 * @param {string} ymd - Date in yyyy-mm-dd or empty
 * @returns {string} dd-mm-yyyy or ''
 */
export function toDdMmYyyy(ymd) {
  if (!ymd || typeof ymd !== 'string') return ''
  const parts = ymd.trim().split('-')
  if (parts.length !== 3) return ''
  const [y, m, d] = parts
  if (y.length === 4 && m.length <= 2 && d.length <= 2) {
    return `${d.padStart(2, '0')}-${m.padStart(2, '0')}-${y}`
  }
  return ''
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
  const monthLabel = MONTHS_3_LOWER[mInt - 1]
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
