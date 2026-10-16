import { useEffect, useState } from 'react'
import { GuestApp } from './GuestApp.tsx'
import { api } from './api/client.ts'
import type { EventSummary, PersonalSnapshot } from './api/types.ts'
import { QueueSkeleton } from './components/Skeletons.tsx'
import { BlockedScreen } from './screens/BlockedScreen.tsx'
import { JoinScreen } from './screens/JoinScreen.tsx'
import { PseudoScreen } from './screens/PseudoScreen.tsx'
import './styles/app.css'

type State =
  | { name: 'loading' }
  | { name: 'join'; code: string | null; event: EventSummary | null }
  | { name: 'pseudo'; event: EventSummary }
  | { name: 'app'; snapshot: PersonalSnapshot }
  | { name: 'ended'; event: EventSummary }
  | { name: 'banned' }

/** Guests arrive from a QR code pointing at /e/CODE; ?c=CODE is accepted too. */
function codeFromUrl(): string | null {
  const path = /^\/e\/([A-Za-z0-9]{4,12})$/.exec(window.location.pathname)
  const code = path?.[1] ?? new URLSearchParams(window.location.search).get('c')

  return code === null || code === '' ? null : code.toUpperCase()
}

export default function App() {
  const [state, setState] = useState<State>({ name: 'loading' })

  useEffect(() => {
    let active = true

    const boot = async () => {
      const code = codeFromUrl()

      try {
        const session = await api.session()
        if (session.guest !== null && session.event !== undefined) {
          if (session.guest.banned) {
            return { name: 'banned' } as State
          }
          if (code === null || code === session.event.joinCode) {
            return { name: 'app', snapshot: await api.queue() } as State
          }
        }
      } catch {
        // A broken session must not block the join screen.
      }

      if (code !== null) {
        try {
          return { name: 'join', code, event: await api.event(code) } as State
        } catch {
          return { name: 'join', code, event: null } as State
        }
      }

      return { name: 'join', code: null, event: null } as State
    }

    void boot().then((next) => {
      if (active) {
        setState(next)
      }
    })

    return () => {
      active = false
    }
  }, [])

  switch (state.name) {
    case 'loading':
      return (
        <div className="screen">
          <QueueSkeleton />
        </div>
      )

    case 'join':
      return (
        <JoinScreen
          initialCode={state.code}
          initialEvent={state.event}
          onReady={(event) => setState(event.status === 'ended' ? { name: 'ended', event } : { name: 'pseudo', event })}
        />
      )

    case 'pseudo':
      return (
        <PseudoScreen
          event={state.event}
          onBack={() => setState({ name: 'join', code: state.event.joinCode, event: state.event })}
          onJoined={(snapshot) => setState({ name: 'app', snapshot })}
        />
      )

    case 'app':
      return (
        <GuestApp
          initial={state.snapshot}
          onLeave={() => setState({ name: 'join', code: codeFromUrl(), event: null })}
        />
      )

    case 'ended':
      return (
        <BlockedScreen
          title="La soirée est terminée"
          text={`« ${state.event.name} » est fini. Plus moyen de pousser une galette, mais la playlist a bien tourné !`}
          action={{ label: 'Rejoindre une autre soirée', onClick: () => setState({ name: 'join', code: null, event: null }) }}
        />
      )

    case 'banned':
      return (
        <BlockedScreen
          title="Tu ne peux plus participer"
          text="L'organisateur a retiré ton accès à cette soirée."
          action={{
            label: 'Rejoindre une autre soirée',
            onClick: () => void api.leave().finally(() => setState({ name: 'join', code: null, event: null })),
          }}
        />
      )
  }
}
