import { useRef, useState } from 'react'
import type { PersonalSnapshot, QueueItemView } from '../api/types.ts'
import { CurrentTrack } from '../components/CurrentTrack.tsx'
import { Logo, SearchIcon, Warning } from '../components/Icons.tsx'
import { QueueRow } from '../components/QueueRow.tsx'
import { QueueSkeleton } from '../components/Skeletons.tsx'
import { UpNext } from '../components/UpNext.tsx'
import { WithdrawSheet } from '../components/WithdrawSheet.tsx'
import { initial, plural } from '../lib/format.ts'
import { useFlip } from '../lib/useFlip.ts'
import type { ConnectionState } from '../lib/useRealtime.ts'

interface Props {
  snapshot: PersonalSnapshot | null
  connection: ConnectionState
  online: boolean
  error: string | null
  onSearch: () => void
  onMine: () => void
  onVote: (itemId: string, value: -1 | 0 | 1) => void
  onWithdraw: (itemId: string) => Promise<void>
}

const CONNECTION_LABEL: Record<ConnectionState, string> = {
  online: 'Temps réel actif',
  connecting: 'Reconnexion en cours…',
  offline: 'Hors ligne',
}

export function QueueScreen({ snapshot, connection, online, error, onSearch, onMine, onVote, onWithdraw }: Props) {
  const listRef = useRef<HTMLOListElement>(null)
  const [withdrawing, setWithdrawing] = useState<QueueItemView | null>(null)
  const pending = snapshot?.queue.filter((item) => item.status === 'pending') ?? []
  const locked = snapshot?.queue.find((item) => item.status !== 'pending') ?? null
  const me = snapshot?.me ?? null
  const live = snapshot?.event.status === 'live'

  useFlip(listRef, pending.map((item) => item.id).join('|'))

  return (
    <div className="screen">
      <div className="glow glow--queue" aria-hidden="true" />

      <header className="queue__header">
        <div className="queue__identity">
          <Logo />
          <div className="queue__event">
            <span className="queue__event-name">{snapshot?.event.name ?? 'Pousseur de Galette'}</span>
            <span className="queue__meta">
              <span
                className={`conn conn--${connection}`}
                title={CONNECTION_LABEL[connection]}
                aria-label={CONNECTION_LABEL[connection]}
                role="img"
              />
              {live ? 'En direct' : 'En pause'}
              {snapshot !== null && ` · ${plural(snapshot.guests, 'convive', 'convives')}`}
            </span>
          </div>
        </div>
        {me !== null && (
          <button type="button" className="mine-link" onClick={onMine}>
            <span className="avatar">{initial(me.pseudo)}</span>
            Mes galettes
          </button>
        )}
      </header>

      {!online && (
        <div className="banner banner--warning">
          <Warning />
          <div className="banner__body">
            <span className="banner__title">Hors ligne</span>
            <span className="banner__text">Tes votes repartiront dès que le réseau revient.</span>
          </div>
        </div>
      )}

      {error !== null && (
        <div className="banner banner--error">
          <Warning />
          {error}
        </div>
      )}

      {snapshot === null ? (
        <QueueSkeleton />
      ) : (
        <>
          {snapshot.event.status === 'paused' && (
            <div className="banner banner--warning">
              <Warning />
              <div className="banner__body">
                <span className="banner__title">Soirée en pause</span>
                <span className="banner__text">Les propositions et les votes reprennent dès que l'hôte relance.</span>
              </div>
            </div>
          )}

          {snapshot.playbackError !== null && (
            <div className="banner banner--warning">
              <Warning />
              <div className="banner__body">
                <span className="banner__title">La musique est en attente</span>
                <span className="banner__text">L'hôte doit relancer Spotify. La file continue de se construire.</span>
              </div>
            </div>
          )}

          {snapshot.current !== null && <CurrentTrack current={snapshot.current} />}
          {locked !== null && <UpNext item={locked} />}

          <div className="queue__heading">
            <h2>File d'attente</h2>
            <span className="queue__count">{plural(pending.length, 'galette', 'galettes')}</span>
          </div>

          {pending.length === 0 ? (
            <div className="empty">
              <span className="empty__title">Personne n'a encore poussé de galette…</span>
              <span className="empty__text">Sois le premier : cherche un titre et pousse-le dans la file.</span>
            </div>
          ) : (
            <ol ref={listRef}>
              {pending.map((item) => (
                <QueueRow
                  key={item.id}
                  item={item}
                  myVote={me?.votes[item.id] ?? 0}
                  mine={me !== null && item.proposedBy?.id === me.id}
                  votable={live && !item.locked}
                  allowDownvotes={snapshot.event.settings.allowDownvotes}
                  onVote={(value) => onVote(item.id, value)}
                  onWithdraw={() => setWithdrawing(item)}
                />
              ))}
            </ol>
          )}
        </>
      )}

      <div className="cta">
        <button type="button" className="btn btn--primary btn--block" onClick={onSearch} disabled={!live}>
          <SearchIcon />
          {live ? 'Proposer une galette' : 'Propositions en pause'}
        </button>
      </div>

      {withdrawing !== null && (
        <WithdrawSheet
          item={withdrawing}
          onCancel={() => setWithdrawing(null)}
          onConfirm={() => onWithdraw(withdrawing.id).finally(() => setWithdrawing(null))}
        />
      )}
    </div>
  )
}
