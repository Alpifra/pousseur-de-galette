import { useEffect, useState } from 'react'
import type { CurrentTrackView } from '../api/types.ts'
import { duration, remaining } from '../lib/format.ts'
import { trackUrl } from '../lib/spotify.ts'
import { CoverArt } from './CoverArt.tsx'
import { SpotifyLink } from './SpotifyLink.tsx'

/** The server only pushes the playback position every few seconds: the bar is extrapolated in between. */
function Progress({
  progressMs,
  durationMs,
  isPlaying,
}: {
  progressMs: number
  durationMs: number
  isPlaying: boolean
}) {
  const [elapsed, setElapsed] = useState(0)

  useEffect(() => {
    if (!isPlaying) {
      return
    }

    const timer = window.setInterval(() => setElapsed((value) => value + 1_000), 1_000)

    return () => window.clearInterval(timer)
  }, [isPlaying])

  const progress = Math.min(durationMs, progressMs + elapsed)
  const ratio = durationMs > 0 ? Math.min(100, (progress / durationMs) * 100) : 0

  return (
    <div className="current__progress">
      <div
        className="progress"
        role="progressbar"
        aria-label="Progression du titre"
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={Math.round(ratio)}
      >
        <div className="progress__bar" style={{ width: `${ratio}%` }} />
      </div>
      <div className="current__times">
        <span>{duration(progress)}</span>
        <span>{remaining(durationMs - progress)}</span>
      </div>
    </div>
  )
}

export function CurrentTrack({ current }: { current: CurrentTrackView }) {
  return (
    <section className="current" aria-label="Titre en cours">
      <div className="current__top">
        <CoverArt url={current.coverUrl} seed={current.spotifyId} className="current__cover" />
        <div className="current__text">
          <span className="kicker">{current.isPlaying ? 'En cours' : 'En pause'}</span>
          <span className="current__title">{current.name ?? 'Titre inconnu'}</span>
          <span className="current__artist">{current.artist}</span>
          <SpotifyLink href={trackUrl(current.spotifyId)} />
          {current.proposedBy !== null ? (
            <span className="current__by">proposé par {current.proposedBy.pseudo}</span>
          ) : (
            !current.fromQueue && <span className="current__by">choix de l'hôte</span>
          )}
        </div>
      </div>
      {/* Remounting on every pushed position resets the extrapolation instead of drifting */}
      <Progress
        key={`${current.spotifyId}:${current.progressMs}`}
        progressMs={current.progressMs}
        durationMs={current.durationMs}
        isPlaying={current.isPlaying}
      />
    </section>
  )
}
