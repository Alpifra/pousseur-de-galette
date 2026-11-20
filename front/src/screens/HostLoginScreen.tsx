import { useState } from 'react'
import { ApiError, hostApi } from '../api/client.ts'
import type { HostMe } from '../api/types.ts'
import { Logo } from '../components/Icons.tsx'

interface Props {
  onLoggedIn: (me: HostMe) => void
}

export function HostLoginScreen({ onLoggedIn }: Props) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const submit = async () => {
    setLoading(true)
    setError(null)
    try {
      onLoggedIn(await hostApi.login(email.trim(), password))
    } catch (failure) {
      setError(
        failure instanceof ApiError && failure.status === 401
          ? 'Email ou mot de passe incorrect.'
          : failure instanceof ApiError
            ? failure.message
            : 'Une erreur est survenue, réessaie dans un instant.',
      )
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="screen">
      <div className="glow glow--join" aria-hidden="true" />

      <div className="join__intro" style={{ paddingTop: 72 }}>
        <Logo size={64} />
        <span className="join__kicker">Espace organisateur</span>
        <h1 className="join__title">Connexion</h1>
        <p className="join__text">Gère tes soirées, ta file d'attente et ton compte Spotify.</p>
      </div>

      <form
        className="join__form"
        onSubmit={(formEvent) => {
          formEvent.preventDefault()
          void submit()
        }}
      >
        <div className="field">
          <label className="field__label" htmlFor="email">
            Email
          </label>
          <input
            id="email"
            className="input input--text"
            type="email"
            value={email}
            onChange={(changeEvent) => setEmail(changeEvent.target.value)}
            autoComplete="username"
            required
            autoFocus
          />
        </div>
        <div className="field">
          <label className="field__label" htmlFor="password">
            Mot de passe
          </label>
          <input
            id="password"
            className="input input--text"
            type="password"
            value={password}
            onChange={(changeEvent) => setPassword(changeEvent.target.value)}
            autoComplete="current-password"
            required
          />
        </div>
        {error !== null && <span className="field__error">{error}</span>}

        <button type="submit" className="btn btn--primary btn--block" disabled={loading}>
          {loading ? <span className="spinner" /> : 'Se connecter'}
        </button>
      </form>
    </div>
  )
}
