import { useState } from 'react'
import { ApiError, api } from '../api/client.ts'
import type { EventSummary, PersonalSnapshot } from '../api/types.ts'
import { ChevronLeft } from '../components/Icons.tsx'

interface Props {
  event: EventSummary
  onBack: () => void
  onJoined: (snapshot: PersonalSnapshot) => void
}

export function PseudoScreen({ event, onBack, onJoined }: Props) {
  const [pseudo, setPseudo] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const submit = async () => {
    if (pseudo.trim().length < 2) {
      setError('Deux caractères minimum.')
      return
    }

    setLoading(true)
    setError(null)
    try {
      onJoined(await api.join(event.joinCode, pseudo.trim()))
    } catch (failure) {
      setError(failure instanceof ApiError ? failure.message : "Une erreur est survenue, réessaie dans un instant.")
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="screen">
      <div className="topbar">
        <button type="button" className="icon-btn" aria-label="Retour" onClick={onBack}>
          <ChevronLeft />
        </button>
      </div>

      <form
        className="pseudo__body"
        onSubmit={(formEvent) => {
          formEvent.preventDefault()
          void submit()
        }}
      >
        <h1 className="pseudo__title">Comment on t'appelle&nbsp;?</h1>
        <div className="field">
          <label className="field__label" htmlFor="pseudo">
            Ton pseudo
          </label>
          <input
            id="pseudo"
            className="input"
            value={pseudo}
            onChange={(changeEvent) => setPseudo(changeEvent.target.value)}
            maxLength={20}
            autoComplete="nickname"
            autoFocus
          />
          {error === null ? (
            <span className="field__hint">Il s'affiche à côté de tes galettes.</span>
          ) : (
            <span className="field__error">{error}</span>
          )}
        </div>

        <div className="pseudo__cta">
          <button type="submit" className="btn btn--primary btn--block" disabled={loading}>
            {loading ? <span className="spinner" /> : "C'est parti"}
          </button>
        </div>
      </form>
    </div>
  )
}
