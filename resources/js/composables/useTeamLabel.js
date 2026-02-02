/**
 * Format team role key or label for display: first letter capital,
 * underscores replaced by space with each word capitalized (e.g. team_leader → Team Leader).
 */
export function formatTeamLabel(str) {
  if (str == null || str === '') return ''
  const s = String(str).trim().toLowerCase().replace(/_/g, ' ')
  return s.replace(/\b\w/g, (c) => c.toUpperCase())
}

/**
 * Return display label for team field (e.g. "Manager Name", "Team Leader Name").
 */
export function teamFieldLabel(rawLabel, key) {
  const formatted = formatTeamLabel(rawLabel || key)
  return formatted ? `${formatted} Name` : ''
}

/**
 * Return short label for placeholder (e.g. "Manager", "Team Leader").
 */
export function teamShortLabel(rawLabel, key) {
  return formatTeamLabel(rawLabel || key)
}
