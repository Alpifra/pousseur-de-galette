import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError, hostApi, isUnauthenticated } from './api/client.ts'
import type { HostMe } from './api/types.ts'
import { QueueSkeleton } from './components/Skeletons.tsx'
import { Toast } from './components/Toast.tsx'
import type { ToastData } from './components/Toast.tsx'
import { HostDashboardScreen } from './screens/HostDashboardScreen.tsx'
import { HostEventsScreen } from './screens/HostEventsScreen.tsx'
import { HostLoginScreen } from './screens/HostLoginScreen.tsx'
import './styles/app.css'

type State = { name: 'loading' } | { name: 'login' } | { name: 'home'; me: HostMe }

/** Set by the Spotify OAuth callback, see SpotifyController::callback(). */
const SPOTIFY_RESULTS: Record<string, Omit<ToastData, 'id'>> = {
  connected: { title: 'Spotify connecté', text: 'La musique peut suivre la file.' },
  not_premium: { title: 'Compte Spotify non Premium', text: 'Le contrôle de la lecture demande Premium.', variant: 'error' },
  denied: { title: 'Connexion Spotify annulée', variant: 'error' },
  invalid_state: { title: 'Connexion Spotify expirée', text: 'Recommence depuis cette page.', variant: 'error' },
  failed: { title: 'Spotify ne répond pas', text: 'Réessaie dans un instant.', variant: 'error' },
}

function codeFromUrl(): string | null {
  return /^\/host\/([A-Za-z0-9]{4,12})\/?$/.exec(window.location.pathname)?.[1]?.toUpperCase() ?? null
}

/** Read once: the URL is cleaned right after so a reload does not show it again. */
function spotifyResult(): ToastData | null {
  const result = new URLSearchParams(window.location.search).get('spotify')
  return result === null ? null : { ...(SPOTIFY_RESULTS[result] ?? SPOTIFY_RESULTS.failed), id: 0 }
}

export default function HostApp() {
  const [state, setState] = useState<State>({ name: 'loading' })
  const [code, setCode] = useState(codeFromUrl)
  const [toast, setToast] = useState<ToastData | null>(spotifyResult)
  const toastId = useRef(0)

  const notify = useCallback((data: Omit<ToastData, 'id'>) => {
    toastId.current += 1
    setToast({ ...data, id: toastId.current })
  }, [])

  const handleFailure = useCallback(
    (failure: unknown) => {
      if (isUnauthenticated(failure)) {
        setState({ name: 'login' })
        return
      }
      notify({
        title: failure instanceof ApiError ? failure.message : 'Une erreur est survenue, réessaie dans un instant.',
        variant: 'error',
      })
    },
    [notify],
  )

  const reload = useCallback(
    () => hostApi.me().then((me) => setState({ name: 'home', me }), handleFailure),
    [handleFailure],
  )

  useEffect(() => {
    if (window.location.search !== '') {
      window.history.replaceState(null, '', window.location.pathname)
    }

    void reload()

    const onPopState = () => setCode(codeFromUrl())
    window.addEventListener('popstate', onPopState)
    return () => window.removeEventListener('popstate', onPopState)
  }, [reload])

  const navigate = useCallback((next: string | null) => {
    window.history.pushState(null, '', next === null ? '/host' : `/host/${next}`)
    setCode(next)
  }, [])

  const backToEvents = useCallback(() => {
    navigate(null)
    void reload()
  }, [navigate, reload])

  const logout = async () => {
    try {
      await hostApi.logout()
    } finally {
      setState({ name: 'login' })
      navigate(null)
    }
  }

  let screen
  switch (state.name) {
    case 'loading':
      screen = (
        <div className="screen">
          <QueueSkeleton />
        </div>
      )
      break

    case 'login':
      screen = <HostLoginScreen onLoggedIn={(me) => setState({ name: 'home', me })} />
      break

    case 'home':
      screen =
        code === null ? (
          <HostEventsScreen
            me={state.me}
            onOpen={navigate}
            onReload={reload}
            onLogout={() => void logout()}
            notify={notify}
            onFailure={handleFailure}
          />
        ) : (
          <HostDashboardScreen
            key={code}
            code={code}
            spotifyConnected={state.me.spotify.connected}
            onBack={backToEvents}
            notify={notify}
            onFailure={handleFailure}
          />
        )
  }

  return (
    <>
      {screen}
      {toast !== null && <Toast toast={toast} onDismiss={() => setToast(null)} />}
    </>
  )
}
