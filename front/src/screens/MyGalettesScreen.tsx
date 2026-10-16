import { useEffect, useState } from 'react'
import { ApiError, api } from '../api/client.ts'
import type { MyProposalsResponse, PersonalSnapshot, QueueItemView } from '../api/types.ts'
import { CoverArt } from '../components/CoverArt.tsx'
import { ArrowUp, ChevronLeft, Warning } from '../components/Icons.tsx'
import { QuotaDots } from '../components/QuotaDots.tsx'
import { initial, ordinal, score, waitLabel } from '../lib/format.ts'

interface Props {
  snapshot: PersonalSnapshot
  onBack: () => void
}

type Group = 'pending' | 'played' | 'removed'

const GROUPS: Array<{ key: Group; label: string }> = [
  { key: 'pending', label: 'En attente' },
  { key: 'played', label: 'Jouées' },
  { key: 'removed', label: 'Retirées' },
]

export function MyGalettesScreen({ snapshot, onBack }: Props) {
  const [data, setData] = useState<MyProposalsResponse | null>(null)
  const [error, setError] = useState<string | null>(null)
  const me = snapshot.me

  useEffect(() => {
    let active = true
    api
      .myProposals()
      .then((response) => {
        if (active) {
          setData(response)
        }
      })
      .catch((failure: unknown) => {
        if (active) {
          setError(failure instanceof ApiError ? failure.message : 'Impossible de charger tes galettes.')
        }
      })
    return () => {
      active = false
    }
  }, [])

  const next = data?.pending.find((item) => item.rank === data.nextRank) ?? null

  return (
    <div className="screen">
      <div className="topbar">
        <button type="button" className="icon-btn" aria-label="Retour à la file" onClick={onBack}>
          <ChevronLeft />
        </button>
      </div>

      <div className="mine__head">
        <span className="avatar avatar--lg">{initial(data?.pseudo ?? me?.pseudo ?? '?')}</span>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
          <h1 className="mine__title">Mes galettes</h1>
          <span className="mine__meta">
            {data?.pseudo ?? me?.pseudo} ·
            {me !== null && <QuotaDots count={me.pendingCount} max={me.maxPending} />}
          </span>
        </div>
      </div>

      {error !== null && (
        <div className="banner banner--error">
          <Warning />
          {error}
        </div>
      )}

      {next !== null && (
        <div className="banner banner--accent">
          <ArrowUp size={28} />
          <div className="banner__body">
            <span className="banner__title">
              {next.rank === 1 ? 'Ta galette passe juste après !' : `Ta galette passe dans ${next.rank - 1} titres !`}
            </span>
            <span className="banner__text">
              {next.track.name} · {waitLabel(next.etaMs)}
            </span>
          </div>
        </div>
      )}

      {data !== null && data.pending.length + data.played.length + data.removed.length === 0 && (
        <div className="empty">
          <span className="empty__title">Pas encore de galette</span>
          <span className="empty__text">Propose un titre : il apparaîtra ici avec son rang et son score.</span>
        </div>
      )}

      {GROUPS.map(({ key, label }) => {
        const items = data?.[key] ?? []
        if (items.length === 0) {
          return null
        }

        return (
          <section key={key} className="group">
            <h2 className="group__title">{label}</h2>
            {items.map((item) => (
              <ProposalRow key={item.id} item={item} group={key} />
            ))}
          </section>
        )
      })}
    </div>
  )
}

function ProposalRow({ item, group }: { item: QueueItemView; group: Group }) {
  const meta =
    group === 'pending'
      ? `${item.track.artist} · ${item.upvotes} vote${item.upvotes > 1 ? 's' : ''} pour`
      : group === 'played'
        ? `${item.track.artist} · jouée`
        : `${item.track.artist} · retirée de la file`

  const chip =
    group === 'pending'
      ? item.status === 'pending'
        ? `${ordinal(item.rank)} · ${score(item.score)}`
        : 'À suivre'
      : group === 'played'
        ? 'Jouée'
        : 'Retirée'

  return (
    <div className={`row row--search${group === 'removed' ? ' row--disabled' : ''}`}>
      <CoverArt url={item.track.coverUrl} seed={item.track.spotifyId} />
      <div className="row__body">
        <span className="row__title">{item.track.name}</span>
        <span className="row__meta">{meta}</span>
      </div>
      <span className={`chip chip--${group}`}>{chip}</span>
    </div>
  )
}
