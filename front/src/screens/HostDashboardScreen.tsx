import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError, hostApi } from '../api/client.ts'
import type { EventStatus, HostDashboard, QueueItemView, RealtimeMessage } from '../api/types.ts'
import { ConfirmSheet } from '../components/ConfirmSheet.tsx'
import { CurrentTrack } from '../components/CurrentTrack.tsx'
import { HostQueueRow } from '../components/HostQueueRow.tsx'
import { ChevronLeft, Copy, Download, Gear, SkipNext, Warning } from '../components/Icons.tsx'
import { QueueSkeleton } from '../components/Skeletons.tsx'
import type { ToastData } from '../components/Toast.tsx'
import { UpNext } from '../components/UpNext.tsx'
import { plural } from '../lib/format.ts'
import { PLAYBACK_ERRORS, STATUS_LABEL } from '../lib/host.ts'
import { useFlip } from '../lib/useFlip.ts'
import { useRealtime } from '../lib/useRealtime.ts'
import { applyPlayback, applyStatus } from '../state/snapshot.ts'
import { HostSettingsScreen } from './HostSettingsScreen.tsx'

interface Props {
  code: string
  spotifyConnected: boolean
  onBack: () => void
  notify: (toast: Omit<ToastData, 'id'>) => void
  onFailure: (failure: unknown) => void
}

type Confirm = { kind: 'end' } | { kind: 'remove'; item: QueueItemView }

export function HostDashboardScreen({ code, spotifyConnected, onBack, notify, onFailure }: Props) {
  const [dashboard, setDashboard] = useState<HostDashboard | null>(null)
  const [editing, setEditing] = useState(false)
  const [confirm, setConfirm] = useState<Confirm | null>(null)
  const [busy, setBusy] = useState(false)
  const listRef = useRef<HTMLOListElement>(null)

  const refresh = useCallback(
    () =>
      hostApi.event(code).then(setDashboard, (failure: unknown) => {
        if (failure instanceof ApiError && failure.code === 'not_found') {
          notify({ title: 'Soirée introuvable', variant: 'error' })
          onBack()
          return
        }
        onFailure(failure)
      }),
    [code, notify, onBack, onFailure],
  )

  useEffect(() => {
    void refresh()
  }, [refresh])

  const onMessage = useCallback((message: RealtimeMessage) => {
    setDashboard((previous) => {
      if (previous === null) {
        return previous
      }
      switch (message.type) {
        case 'queue':
          return { ...previous, snapshot: message }
        case 'playback':
          return { ...previous, snapshot: applyPlayback(previous.snapshot, message) }
        case 'status':
          return {
            ...previous,
            event: { ...previous.event, status: message.status },
            snapshot: applyStatus(previous.snapshot, message.status),
          }
      }
    })
  }, [])

  const connection = useRealtime({
    hub: dashboard?.realtime.hub ?? null,
    topic: dashboard?.realtime.topic ?? null,
    onMessage,
    onResync: () => void refresh(),
  })

  useEffect(() => {
    if (dashboard?.realtime.hub != null && connection === 'online') {
      return
    }
    const timer = window.setInterval(() => void refresh(), 10_000)
    return () => window.clearInterval(timer)
  }, [dashboard?.realtime.hub, connection, refresh])

  const snapshot = dashboard?.snapshot ?? null
  const pending = snapshot?.queue.filter((item) => item.status === 'pending') ?? []
  const locked = snapshot?.queue.find((item) => item.status !== 'pending') ?? null
  useFlip(listRef, pending.map((item) => item.id).join('|'))

  const run = async (action: () => Promise<HostDashboard | void>, success?: Omit<ToastData, 'id'>) => {
    setBusy(true)
    try {
      const next = await action()
      if (next) {
        setDashboard(next)
      }
      if (success) {
        notify(success)
      }
    } catch (failure) {
      onFailure(failure)
    } finally {
      setBusy(false)
    }
  }

  const changeStatus = (status: EventStatus) =>
    run(() => hostApi.updateEvent(code, { status }), { title: `Soirée : ${STATUS_LABEL[status].toLowerCase()}` })

  if (dashboard === null || snapshot === null) {
    return (
      <div className="screen">
        <div className="topbar">
          <button type="button" className="icon-btn" aria-label="Retour" onClick={onBack}>
            <ChevronLeft />
          </button>
        </div>
        <QueueSkeleton />
      </div>
    )
  }

  if (editing) {
    return (
      <HostSettingsScreen
        event={dashboard.event}
        onBack={() => setEditing(false)}
        onSaved={(next) => {
          setDashboard(next)
          setEditing(false)
          notify({ title: 'Paramètres enregistrés' })
        }}
        onFailure={onFailure}
      />
    )
  }

  const { event } = dashboard
  const status = snapshot.event.status
  const ended = status === 'ended'
  const playbackError = snapshot.playbackError === null ? null : (PLAYBACK_ERRORS[snapshot.playbackError] ?? PLAYBACK_ERRORS.spotify_error)

  return (
    <div className="screen">
      <div className="glow glow--queue" aria-hidden="true" />

      <header className="topbar host__topbar">
        <button type="button" className="icon-btn" aria-label="Retour aux soirées" onClick={onBack}>
          <ChevronLeft />
        </button>
        <div className="queue__event" style={{ flex: 1 }}>
          <span className="queue__event-name">{snapshot.event.name}</span>
          <span className="queue__meta">
            <span className={`conn conn--${connection}`} role="img" aria-label={connection === 'online' ? 'Temps réel actif' : 'Hors ligne'} />
            {STATUS_LABEL[status]} · {plural(snapshot.guests, 'convive', 'convives')}
          </span>
        </div>
        <button type="button" className="icon-btn" aria-label="Paramètres" onClick={() => setEditing(true)}>
          <Gear />
        </button>
      </header>

      <div className="host__actions">
        {status === 'draft' && (
          <button type="button" className="btn btn--primary btn--block" disabled={busy} onClick={() => void changeStatus('live')}>
            Ouvrir la soirée
          </button>
        )}
        {status === 'live' && (
          <button type="button" className="btn btn--outline btn--block" disabled={busy} onClick={() => void changeStatus('paused')}>
            Mettre en pause
          </button>
        )}
        {status === 'paused' && (
          <button type="button" className="btn btn--primary btn--block" disabled={busy} onClick={() => void changeStatus('live')}>
            Reprendre
          </button>
        )}
        {!ended && (
          <button type="button" className="btn btn--ghost" disabled={busy} onClick={() => setConfirm({ kind: 'end' })}>
            Terminer
          </button>
        )}
      </div>

      {ended && (
        <div className="banner banner--warning">
          <Warning />
          <div className="banner__body">
            <span className="banner__title">Soirée terminée</span>
            <span className="banner__text">Les convives ne peuvent plus proposer ni voter.</span>
          </div>
        </div>
      )}

      {!spotifyConnected ? (
        <div className="banner banner--warning">
          <Warning />
          <div className="banner__body">
            <span className="banner__title">Spotify n'est pas connecté</span>
            <span className="banner__text">
              <a className="host__link" href={hostApi.connectSpotifyUrl}>
                Connecter mon compte
              </a>{' '}
              pour que la file passe à la lecture.
            </span>
          </div>
        </div>
      ) : (
        playbackError !== null &&
        !ended && (
          <div className="banner banner--warning">
            <Warning />
            <div className="banner__body">
              <span className="banner__title">{playbackError.title}</span>
              <span className="banner__text">{playbackError.text}</span>
            </div>
          </div>
        )
      )}

      {snapshot.current !== null && (
        <>
          <CurrentTrack current={snapshot.current} />
          <div className="host__skip">
            <span className="queue__count">{event.device !== null && `Sur ${event.device}`}</span>
            <button
              type="button"
              className="btn btn--outline btn--small"
              disabled={busy || !spotifyConnected}
              onClick={() => void run(() => hostApi.skip(code), { title: 'Titre suivant demandé à Spotify' })}
            >
              <SkipNext />
              Passer
            </button>
          </div>
        </>
      )}
      {locked !== null && <UpNext item={locked} />}

      <div className="queue__heading">
        <h2>File d'attente</h2>
        <span className="queue__count">{plural(pending.length, 'galette', 'galettes')}</span>
      </div>

      {pending.length === 0 ? (
        <div className="empty">
          <span className="empty__title">La file est vide</span>
          <span className="empty__text">Partage le QR code ci-dessous pour que les convives poussent leurs galettes.</span>
        </div>
      ) : (
        <ol ref={listRef}>
          {pending.map((item) => (
            <HostQueueRow
              key={item.id}
              item={item}
              disabled={busy || ended}
              onPin={(pinned) =>
                void run(() => hostApi.pinItem(code, item.id, pinned), { title: pinned ? 'Titre épinglé en tête' : 'Titre désépinglé' })
              }
              onRemove={() => setConfirm({ kind: 'remove', item })}
            />
          ))}
        </ol>
      )}

      <section className="section host__share" aria-label="Inviter les convives">
        <span className="section__title">Inviter les convives</span>
        <div className="host__qr">
          <img src={hostApi.qrCodeUrl(code)} alt={`QR code de la soirée ${event.joinCode}`} width={200} height={200} />
        </div>
        <span className="host__code">{event.joinCode}</span>
        <span className="row__meta" style={{ textAlign: 'center' }}>
          {event.joinUrl}
        </span>
        <div className="host__inline">
          <button
            type="button"
            className="btn btn--outline btn--small btn--block"
            onClick={() =>
              void navigator.clipboard
                .writeText(event.joinUrl)
                .then(() => notify({ title: 'Lien copié' }))
                .catch(() => notify({ title: 'Copie impossible', variant: 'error' }))
            }
          >
            <Copy />
            Copier le lien
          </button>
          <a className="btn btn--outline btn--small btn--block" href={hostApi.qrCodeUrl(code)} download={`galette-${event.joinCode}.svg`}>
            <Download />
            QR code
          </a>
        </div>
      </section>

      {confirm?.kind === 'end' && (
        <ConfirmSheet
          title="Terminer la soirée ?"
          text="La file sera fermée pour de bon : les convives ne pourront plus proposer ni voter."
          confirmLabel="Terminer la soirée"
          onCancel={() => setConfirm(null)}
          onConfirm={() => changeStatus('ended').finally(() => setConfirm(null))}
        />
      )}
      {confirm?.kind === 'remove' && (
        <ConfirmSheet
          title={`Retirer « ${confirm.item.track.name} » ?`}
          text={
            confirm.item.proposedBy !== null
              ? `${confirm.item.proposedBy.pseudo} verra sa galette marquée comme retirée.`
              : 'Le titre quittera la file.'
          }
          confirmLabel="Retirer de la file"
          onCancel={() => setConfirm(null)}
          onConfirm={() =>
            run(() => hostApi.removeItem(code, confirm.item.id), { title: 'Titre retiré' }).finally(() => setConfirm(null))
          }
        />
      )}
    </div>
  )
}
