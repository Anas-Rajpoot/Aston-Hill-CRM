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
  const yInt = parseInt(y, 10)
  let mInt = parseInt(m, 10)
  let dInt = parseInt(d, 10)
  if (Number.isNaN(yInt) || Number.isNaN(mInt) || Number.isNaN(dInt)) return ''
  if (yInt < 100) yInt += 2000
  const date = new Date(yInt, mInt - 1, dInt)
  if (Number.isNaN(date.getTime())) return ''
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}
