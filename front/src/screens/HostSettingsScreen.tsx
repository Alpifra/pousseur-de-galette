import { useState } from 'react'
import { ApiError, hostApi } from '../api/client.ts'
import type { EventSettingsView, HostDashboard, HostEventView } from '../api/types.ts'
import { ChevronLeft } from '../components/Icons.tsx'

interface Props {
  event: HostEventView
  onBack: () => void
  onSaved: (dashboard: HostDashboard) => void
  onFailure: (failure: unknown) => void
}

/** Numeric settings are edited in the unit the host thinks in, the API stores milliseconds. */
interface NumberField {
  key: Exclude<keyof EventSettingsView, 'allowDownvotes' | 'blockExplicit'>
  label: string
  hint: string
  unit: number
  min: number
  max: number
}

const NUMBER_FIELDS: NumberField[] = [
  { key: 'maxPendingPerGuest', label: 'Galettes en attente par convive', hint: "Au-delà, il doit attendre qu'une des siennes passe.", unit: 1, min: 1, max: 50 },
  { key: 'rejectionThreshold', label: 'Retrait automatique à un score de', hint: "Un titre épinglé n'est jamais retiré.", unit: 1, min: -100, max: -1 },
  { key: 'maxActiveDownvotesPerGuest', label: 'Votes contre en cours par convive', hint: 'Au-delà, il doit en annuler un ou attendre que le titre passe.', unit: 1, min: 1, max: 50 },
  { key: 'replayDelayMinutes', label: 'Délai avant de rejouer un titre (min)', hint: "0 pour l'autoriser tout de suite.", unit: 1, min: 0, max: 1440 },
  { key: 'maxTrackDurationMs', label: "Durée maximale d'un titre (min)", hint: 'Évite les versions de 20 minutes.', unit: 60_000, min: 1, max: 60 },
  { key: 'lockLeadMs', label: 'Envoi du titre suivant à Spotify (s avant la fin)', hint: 'Entre 5 et 60 s. Le titre est verrouillé à ce moment-là.', unit: 1_000, min: 5, max: 60 },
]

export function HostSettingsScreen({ event, onBack, onSaved, onFailure }: Props) {
  const [name, setName] = useState(event.name)
  const [date, setDate] = useState(event.scheduledFor ?? '')
  const [toggles, setToggles] = useState({
    allowDownvotes: event.settings.allowDownvotes,
    blockExplicit: event.settings.blockExplicit,
  })
  const [numbers, setNumbers] = useState(() =>
    Object.fromEntries(NUMBER_FIELDS.map((field) => [field.key, String(event.settings[field.key] / field.unit)])),
  )
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const save = async () => {
    const settings: Partial<EventSettingsView> = { ...toggles }
    for (const field of NUMBER_FIELDS) {
      const value = Number(numbers[field.key])
      if (!Number.isInteger(value) || value < field.min || value > field.max) {
        setError(`« ${field.label} » doit être un nombre entier entre ${field.min} et ${field.max}.`)
        return
      }
      settings[field.key] = value * field.unit
    }

    setSaving(true)
    setError(null)
    try {
      onSaved(await hostApi.updateEvent(event.joinCode, { name: name.trim(), scheduledFor: date === '' ? null : date, settings }))
    } catch (failure) {
      if (failure instanceof ApiError && failure.status === 422) {
        setError(failure.message)
      } else {
        onFailure(failure)
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="screen">
      <div className="topbar">
        <button type="button" className="icon-btn" aria-label="Retour au tableau de bord" onClick={onBack}>
          <ChevronLeft />
        </button>
        <span className="queue__event-name">Paramètres</span>
      </div>

      <form
        className="host__settings"
        onSubmit={(formEvent) => {
          formEvent.preventDefault()
          void save()
        }}
      >
        <div className="field">
          <label className="field__label" htmlFor="event-name">
            Nom de la soirée
          </label>
          <input
            id="event-name"
            className="input input--text"
            value={name}
            onChange={(changeEvent) => setName(changeEvent.target.value)}
            maxLength={120}
            minLength={2}
            required
          />
        </div>
        <div className="field">
          <label className="field__label" htmlFor="event-date">
            Date
          </label>
          <input
            id="event-date"
            className="input input--text"
            type="date"
            value={date}
            onChange={(changeEvent) => setDate(changeEvent.target.value)}
          />
        </div>

        <label className="toggle">
          <span className="toggle__text">
            <span className="toggle__label">Votes négatifs</span>
            <span className="field__hint">Les convives peuvent faire descendre un titre.</span>
          </span>
          <input
            type="checkbox"
            className="toggle__input"
            checked={toggles.allowDownvotes}
            onChange={(changeEvent) => setToggles({ ...toggles, allowDownvotes: changeEvent.target.checked })}
          />
        </label>
        <label className="toggle">
          <span className="toggle__text">
            <span className="toggle__label">Bloquer les titres explicites</span>
            <span className="field__hint">Ils restent visibles dans la recherche, sans pouvoir être proposés.</span>
          </span>
          <input
            type="checkbox"
            className="toggle__input"
            checked={toggles.blockExplicit}
            onChange={(changeEvent) => setToggles({ ...toggles, blockExplicit: changeEvent.target.checked })}
          />
        </label>

        {NUMBER_FIELDS.map((field) => (
          <div key={field.key} className="field">
            <label className="field__label" htmlFor={field.key}>
              {field.label}
            </label>
            <input
              id={field.key}
              className="input input--text"
              type="number"
              inputMode="numeric"
              min={field.min}
              max={field.max}
              step={1}
              value={numbers[field.key]}
              onChange={(changeEvent) => setNumbers({ ...numbers, [field.key]: changeEvent.target.value })}
            />
            <span className="field__hint">{field.hint}</span>
          </div>
        ))}

        {error !== null && <span className="field__error">{error}</span>}

        <div className="cta">
          <button type="submit" className="btn btn--primary btn--block" disabled={saving || name.trim().length < 2}>
            {saving ? <span className="spinner" /> : 'Enregistrer'}
          </button>
        </div>
      </form>
    </div>
  )
}
