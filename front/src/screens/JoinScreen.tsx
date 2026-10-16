import { useState } from 'react'
import { ApiError, api } from '../api/client.ts'
import type { EventSummary } from '../api/types.ts'
import { ArrowUp, Check } from '../components/Icons.tsx'

interface Props {
  initialCode: string | null
  initialEvent: EventSummary | null
  onReady: (event: EventSummary) => void
}

export function JoinScreen({ initialCode, initialEvent, onReady }: Props) {
  const [code, setCode] = useState(initialCode ?? '')
  const [scanned, setScanned] = useState(initialEvent !== null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const submit = async (candidate: string) => {
    const cleaned = candidate.replace(/[^A-Za-z0-9]/g, '').toUpperCase()
    if (cleaned.length < 4) {
      setError('Le code fait au moins 4 caractères.')
      return
    }

    setLoading(true)
    setError(null)
    try {
      onReady(await api.event(cleaned))
    } catch (failure) {
      setError(
        failure instanceof ApiError && failure.code === 'not_found'
          ? "Aucune soirée ne porte ce code. Vérifie auprès de l'organisateur."
          : failure instanceof ApiError
            ? failure.message
            : "Une erreur est survenue, réessaie dans un instant.",
      )
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="screen">
      <div className="glow glow--join" aria-hidden="true" />

      <div className="join__disc" aria-hidden="true">
        <div className="join__vinyl">
          <div className="join__label">
            <ArrowUp size={40} />
          </div>
        </div>
      </div>

      <div className="join__intro">
        <span className="join__kicker">{initialEvent !== null ? 'Tu es invité·e à' : 'Pousseur de Galette'}</span>
        <h1 className="join__title">{initialEvent?.name ?? 'Rejoins la soirée'}</h1>
        <p className="join__text">
          Propose tes titres, vote pour ceux des autres : c'est toi qui fais la playlist ce soir.
        </p>
      </div>

      <form
        className="join__form"
        onSubmit={(event) => {
          event.preventDefault()
          void submit(code)
        }}
      >
        <label className="field__label" htmlFor="code">
          Code de la soirée
        </label>
        {scanned && initialEvent !== null ? (
          <div className="code-row">
            <input id="code" className="code-row__input" value={initialEvent.joinCode} readOnly />
            <span className="join__qr">
              <Check />
              Via QR code
            </span>
          </div>
        ) : (
          <input
            id="code"
            className="input input--code"
            value={code}
            onChange={(event) => setCode(event.target.value)}
            placeholder="GALET4821"
            autoCapitalize="characters"
            autoComplete="off"
            inputMode="text"
            maxLength={12}
            autoFocus={initialCode === null}
          />
        )}
        {error !== null && <span className="field__error">{error}</span>}

        <button type="submit" className="btn btn--primary btn--block" disabled={loading}>
          {loading ? <span className="spinner" /> : 'Rejoindre la soirée'}
        </button>
        {scanned && (
          <button
            type="button"
            className="btn btn--ghost"
            onClick={() => {
              setScanned(false)
              setCode('')
            }}
          >
            Saisir un autre code
          </button>
        )}
        <span className="join__legal">Musique diffusée via Spotify · Propulsé par Spotify</span>
      </form>
    </div>
  )
}
