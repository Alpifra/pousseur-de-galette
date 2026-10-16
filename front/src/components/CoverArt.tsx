import { coverGradient } from '../lib/format.ts'

interface Props {
  url: string | null
  seed: string
  className?: string
}

export function CoverArt({ url, seed, className = 'cover' }: Props) {
  if (url === null) {
    return <div className={className} style={{ background: coverGradient(seed) }} role="img" aria-label="Pochette" />
  }

  return <img className={className} src={url} alt="" loading="lazy" decoding="async" />
}
