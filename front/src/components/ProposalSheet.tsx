import { useState } from 'react'
import type { PersonalSnapshot, SearchResult } from '../api/types.ts'
import { duration, ordinal, waitLabel } from '../lib/format.ts'
import { estimate } from '../state/estimate.ts'
import { trackUrl } from '../lib/spotify.ts'
import { CoverArt } from './CoverArt.tsx'
import { ArrowUp } from './Icons.tsx'
import { SpotifyLink } from './SpotifyLink.tsx'

interface Props {
  result: SearchResult
  snapshot: PersonalSnapshot
  onCancel: () => void
  onConfirm: () => Promise<void>
}

export function ProposalSheet({ result, snapshot, onCancel, onConfirm }: Props) {
  const [loading, setLoading] = useState(false)
  const position = estimate(snapshot)
  const me = snapshot.me
  const alreadyQueued = result.state === 'queued'

  return (
    <>
      <div className="overlay" onClick={onCancel} aria-hidden="true" />
      <section className="sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-title">
        <div className="sheet__grip" aria-hidden="true" />

        <div className="sheet__track">
          <CoverArt url={result.coverUrl} seed={result.spotifyId} className="sheet__cover" />
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4, minWidth: 0 }}>
            <span className="kicker" style={{ color: 'var(--text-secondary)' }}>
              {alreadyQueued ? 'Déjà dans la file' : 'Ta galette'}
            </span>
            <h2 id="sheet-title" className="sheet__title">
              {result.name}
            </h2>
            <span className="row__meta">
              {result.artist} · {duration(result.durationMs)}
            </span>
            <SpotifyLink href={trackUrl(result.spotifyId)} />
          </div>
        </div>

        {!alreadyQueued && (
          <div className="stats">
            <div className="stat">
              <span className="stat__label">Position estimée</span>
              <span className="stat__value stat__value--accent">{ordinal(position.rank)}</span>
            </div>
            <div className="stat">
              <span className="stat__label">Passage estimé</span>
              <span className="stat__value">{waitLabel(position.etaMs)}</span>
            </div>
          </div>
        )}

        <p className="sheet__text">
          {alreadyQueued
            ? 'Ce titre est déjà proposé : ton vote pour va le faire remonter dans la file.'
            : me === null
              ? "Ton vote pour est compté d'office."
              : `Ton vote pour est compté d'office. Ce sera ta ${ordinal(me.pendingCount + 1)} galette en attente sur ${me.maxPending}.`}
        </p>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <button
            type="button"
            className="btn btn--primary btn--block"
            disabled={loading}
            onClick={() => {
              setLoading(true)
              void onConfirm().finally(() => setLoading(false))
            }}
          >
            {loading ? (
              <span className="spinner" />
            ) : (
              <>
                <ArrowUp />
                {alreadyQueued ? 'Voter pour cette galette' : 'Pousser la galette'}
              </>
            )}
          </button>
          <button type="button" className="btn btn--ghost" onClick={onCancel}>
            Annuler
          </button>
        </div>
      </section>
    </>
  )
}
