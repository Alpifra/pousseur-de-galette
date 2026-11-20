import { useState } from 'react'
import { hostApi } from '../api/client.ts'
import type { HostMe } from '../api/types.ts'
import type { ToastData } from '../components/Toast.tsx'
import { Check, Logo, Warning } from '../components/Icons.tsx'
import { STATUS_LABEL, longDate } from '../lib/host.ts'

interface Props {
  me: HostMe
  onOpen: (code: string) => void
  onReload: () => Promise<void>
  onLogout: () => void
  notify: (toast: Omit<ToastData, 'id'>) => void
  onFailure: (failure: unknown) => void
}

export function HostEventsScreen({ me, onOpen, onReload, onLogout, notify, onFailure }: Props) {
  const [name, setName] = useState('')
  const [date, setDate] = useState('')
  const [creating, setCreating] = useState(false)
  const [disconnecting, setDisconnecting] = useState(false)

  const create = async () => {
    setCreating(true)
    try {
      const created = await hostApi.createEvent(name.trim(), date === '' ? null : date)
      onOpen(created.event.joinCode)
    } catch (failure) {
      onFailure(failure)
    } finally {
      setCreating(false)
    }
  }

  const disconnect = async () => {
    setDisconnecting(true)
    try {
      await hostApi.disconnectSpotify()
      await onReload()
      notify({ title: 'Spotify déconnecté' })
    } catch (failure) {
      onFailure(failure)
    } finally {
      setDisconnecting(false)
    }
  }

  return (
    <div className="screen">
      <div className="glow glow--queue" aria-hidden="true" />

      <header className="queue__header">
        <div className="queue__identity">
          <Logo />
          <div className="queue__event">
            <span className="queue__event-name">Mes soirées</span>
            <span className="queue__meta">{me.email}</span>
          </div>
        </div>
        <button type="button" className="mine-link" style={{ paddingLeft: 14 }} onClick={onLogout}>
          Déconnexion
        </button>
      </header>

      <section className="section host__block" aria-label="Compte Spotify">
        <span className="section__title">Spotify</span>
        {me.spotify.connected ? (
          <div className="card">
            <div className="card__body">
              <span className="card__title">
                <Check /> Compte connecté
              </span>
              <span className="card__text">
                {me.spotify.premium === false
                  ? "Ce compte n'est pas Premium : la lecture ne peut pas être pilotée."
                  : "La musique suit la file dès qu'un appareil joue sur ce compte."}
              </span>
            </div>
            <button type="button" className="btn btn--outline btn--small" disabled={disconnecting} onClick={() => void disconnect()}>
              Déconnecter
            </button>
          </div>
        ) : (
          <div className="card card--warning">
            <Warning />
            <div className="card__body">
              <span className="card__title">Spotify n'est pas connecté</span>
              <span className="card__text">Sans lui, la file se construit mais aucun titre n'est envoyé à la lecture.</span>
            </div>
            <a className="btn btn--primary btn--small" href={hostApi.connectSpotifyUrl}>
              Connecter
            </a>
          </div>
        )}
      </section>

      <section className="section host__block" aria-label="Nouvelle soirée">
        <span className="section__title">Nouvelle soirée</span>
        <form
          className="host__form"
          onSubmit={(formEvent) => {
            formEvent.preventDefault()
            void create()
          }}
        >
          <input
            className="input input--text"
            value={name}
            onChange={(changeEvent) => setName(changeEvent.target.value)}
            placeholder="Mariage de Léa et Tom"
            maxLength={120}
            aria-label="Nom de la soirée"
            required
            minLength={2}
          />
          <div className="host__inline">
            <input
              className="input input--text"
              type="date"
              value={date}
              onChange={(changeEvent) => setDate(changeEvent.target.value)}
              aria-label="Date (facultative)"
            />
            <button type="submit" className="btn btn--primary btn--small" disabled={creating || name.trim().length < 2}>
              {creating ? <span className="spinner" /> : 'Créer'}
            </button>
          </div>
        </form>
      </section>

      <div className="queue__heading">
        <h2>Soirées</h2>
        <span className="queue__count">{me.events.length}</span>
      </div>

      {me.events.length === 0 ? (
        <div className="empty">
          <span className="empty__title">Aucune soirée pour l'instant</span>
          <span className="empty__text">Crée ta première soirée : tu obtiendras un code et un QR code à partager.</span>
        </div>
      ) : (
        <ul className="host__events">
          {me.events.map((event) => (
            <li key={event.id}>
              <button type="button" className="row host__event" onClick={() => onOpen(event.joinCode)}>
                <div className="row__body">
                  <span className="row__title">{event.name}</span>
                  <span className="row__meta">
                    {event.joinCode}
                    {event.scheduledFor !== null && ` · ${longDate(event.scheduledFor)}`}
                  </span>
                </div>
                <span className={`chip chip--${event.status}`}>{STATUS_LABEL[event.status]}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
