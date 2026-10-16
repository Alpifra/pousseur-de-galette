export function duration(ms: number): string {
  const total = Math.max(0, Math.round(ms / 1000))
  return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`
}

export function remaining(ms: number): string {
  return `−${duration(ms)}`
}

export function ordinal(rank: number): string {
  return rank === 1 ? '1er' : `${rank}e`
}

export function waitLabel(ms: number | null): string {
  if (ms === null) {
    return '—'
  }
  const minutes = Math.round(ms / 60_000)
  if (minutes < 1) {
    return 'bientôt'
  }
  if (minutes < 60) {
    return `≈ ${minutes} min`
  }
  const hours = Math.floor(minutes / 60)
  return `≈ ${hours} h ${String(minutes % 60).padStart(2, '0')}`
}

export function score(value: number): string {
  return value > 0 ? `+${value}` : String(value)
}

export function initial(pseudo: string): string {
  return [...pseudo.trim()][0]?.toUpperCase() ?? '?'
}

export function plural(count: number, one: string, many: string): string {
  return `${count} ${count > 1 ? many : one}`
}

/** Album art is optional: a deterministic two-tone gradient keeps rows readable without it. */
const COVER_HUES = [12, 38, 155, 198, 252, 288, 325]

export function coverGradient(seed: string): string {
  let hash = 0
  for (let i = 0; i < seed.length; i += 1) {
    hash = (hash * 31 + seed.charCodeAt(i)) % 100_000
  }
  const first = COVER_HUES[hash % COVER_HUES.length]
  const second = COVER_HUES[(hash >> 3) % COVER_HUES.length]
  const angle = 35 + (hash % 5) * 30
  return `linear-gradient(${angle}deg, hsl(${first} 55% 45%) 50%, hsl(${second} 45% 24%) 50%)`
}
