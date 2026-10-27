import { useState } from 'react'
import type { QueueItemView } from '../api/types.ts'
import { plural } from '../lib/format.ts'
import { CoverArt } from './CoverArt.tsx'
import { Trash } from './Icons.tsx'

interface Props {
  item: QueueItemView
  onCancel: () => void
  onConfirm: () => Promise<void>
}

export function WithdrawSheet({ item, onCancel, onConfirm }: Props) {
  const [loading, setLoading] = useState(false)
  // The author's own upvote leaves with the track: only the other guests' votes are lost
  const received = item.upvotes - 1 + item.downvotes

  return (
    <>
      <div className="overlay" onClick={onCancel} aria-hidden="true" />
      <section className="sheet" role="alertdialog" aria-modal="true" aria-labelledby="withdraw-title">
        <div className="sheet__grip" aria-hidden="true" />

        <div className="sheet__track">
          <CoverArt url={item.track.coverUrl} seed={item.track.spotifyId} className="sheet__cover" />
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4, minWidth: 0 }}>
            <span className="kicker" style={{ color: 'var(--text-secondary)' }}>
              Ta galette
            </span>
            <h2 id="withdraw-title" className="sheet__title">
              Retirer « {item.track.name} » ?
            </h2>
            <span className="row__meta">{item.track.artist}</span>
          </div>
        </div>

        <p className="sheet__text">
          {received > 0
            ? `Elle quittera la file et perdra ${plural(received, 'vote reçu', 'votes reçus')}. Tu récupères une place dans ton quota.`
            : 'Elle quittera la file. Tu récupères une place dans ton quota.'}
        </p>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <button
            type="button"
            className="btn btn--danger btn--block"
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
                <Trash />
                Retirer ma galette
              </>
            )}
          </button>
          <button type="button" className="btn btn--ghost" onClick={onCancel}>
            Garder
          </button>
        </div>
      </section>
    </>
  )
}
