import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError, api } from './api/client.ts'
import type { PersonalSnapshot, RealtimeMessage, SearchResult } from './api/types.ts'
import { Toast } from './components/Toast.tsx'
import type { ToastData } from './components/Toast.tsx'
import { ordinal } from './lib/format.ts'
import { useOnline } from './lib/useOnline.ts'
import { useRealtime } from './lib/useRealtime.ts'
import { BlockedScreen } from './screens/BlockedScreen.tsx'
import { MyGalettesScreen } from './screens/MyGalettesScreen.tsx'
import { QueueScreen } from './screens/QueueScreen.tsx'
import { SearchScreen } from './screens/SearchScreen.tsx'
import { applyPlayback, applyStatus, applyVote, mergeSnapshot } from './state/snapshot.ts'

type Screen = 'queue' | 'search' | 'mine'

interface Props {
  initial: PersonalSnapshot
  onLeave: () => void
}

export function GuestApp({ initial, onLeave }: Props) {
  const [snapshot, setSnapshot] = useState(initial)
  const [screen, setScreen] = useState<Screen>('queue')
  const [banned, setBanned] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [toast, setToast] = useState<ToastData | null>(null)
  const online = useOnline()
  const toastId = useRef(0)

  const notify = useCallback((data: Omit<ToastData, 'id'>) => {
    toastId.current += 1
    setToast({ ...data, id: toastId.current })
  }, [])

  const handleFailure = useCallback(
    (failure: unknown) => {
      if (failure instanceof ApiError) {
        if (failure.code === 'invalid_session') {
          onLeave()
          return
        }
        if (failure.code === 'guest_banned') {
          setBanned(true)
          return
        }
        notify({ title: failure.message, variant: 'error' })
        return
      }
      notify({ title: "Une erreur est survenue, réessaie dans un instant.", variant: 'error' })
    },
    [notify, onLeave],
  )

  const refresh = useCallback(async () => {
    try {
      setSnapshot(await api.queue())
      setError(null)
    } catch (failure) {
      if (failure instanceof ApiError && (failure.code === 'invalid_session' || failure.code === 'guest_banned')) {
        handleFailure(failure)
        return
      }
      setError('File non synchronisée : on réessaie…')
    }
  }, [handleFailure])

  const onMessage = useCallback((message: RealtimeMessage) => {
    setSnapshot((previous) => {
      switch (message.type) {
        case 'queue':
          return mergeSnapshot(previous, message)
        case 'playback':
          return applyPlayback(previous, message)
        case 'status':
          return applyStatus(previous, message.status)
      }
    })
    setError(null)
  }, [])

  const connection = useRealtime({
    hub: snapshot.realtime.hub,
    topic: snapshot.realtime.topic,
    onMessage,
    onResync: () => void refresh(),
  })

  useEffect(() => {
    const resync = () => {
      if (document.visibilityState === 'visible') {
        void refresh()
      }
    }
    document.addEventListener('visibilitychange', resync)
    window.addEventListener('online', resync)
    return () => {
      document.removeEventListener('visibilitychange', resync)
      window.removeEventListener('online', resync)
    }
  }, [refresh])

  /** Without a hub (production does not have one yet, see TODO.md) the queue is polled. */
  useEffect(() => {
    if (snapshot.realtime.hub !== null && connection === 'online') {
      return
    }
    const timer = window.setInterval(() => void refresh(), 10_000)
    return () => window.clearInterval(timer)
  }, [snapshot.realtime.hub, connection, refresh])

  const vote = (itemId: string, value: -1 | 0 | 1) => {
    const previous = snapshot
    setSnapshot(applyVote(previous, itemId, value))
    api
      .vote(itemId, value)
      .then((response) => setSnapshot(response.snapshot))
      .catch((failure: unknown) => {
        setSnapshot(previous)
        handleFailure(failure)
      })
  }

  const withdraw = async (itemId: string) => {
    try {
      setSnapshot((await api.withdraw(itemId)).snapshot)
      notify({ title: 'Galette retirée', text: 'Ta place dans le quota est libérée.' })
    } catch (failure) {
      handleFailure(failure)
    }
  }

  const propose = async (result: SearchResult) => {
    try {
      const response = await api.propose(result.spotifyId)
      setSnapshot(response.snapshot)
      setScreen('queue')
      notify({
        title: response.alreadyQueued ? 'Ton vote est compté !' : 'Ta galette est dans la file !',
        text:
          response.item === null
            ? result.name
            : `${result.name} · ${ordinal(response.item.rank)} position`,
        cover: { url: result.coverUrl, seed: result.spotifyId },
        action: { label: 'Voir', onClick: () => setScreen('mine') },
      })
    } catch (failure) {
      handleFailure(failure)
    }
  }

  if (banned) {
    return (
      <BlockedScreen
        title="Tu ne peux plus participer"
        text="L'organisateur a retiré ton accès à cette soirée. Si c'est une erreur, va le voir : il peut te réinviter."
      />
    )
  }

  if (snapshot.event.status === 'ended') {
    return (
      <BlockedScreen
        title="La soirée est terminée"
        text={`Merci d'avoir poussé tes galettes à « ${snapshot.event.name} » ! La file est fermée.`}
        action={{ label: 'Quitter', onClick: () => void api.leave().finally(onLeave) }}
      />
    )
  }

  return (
    <>
      {screen === 'search' ? (
        <SearchScreen snapshot={snapshot} onBack={() => setScreen('queue')} onPropose={propose} />
      ) : screen === 'mine' ? (
        <MyGalettesScreen snapshot={snapshot} onBack={() => setScreen('queue')} />
      ) : (
        <QueueScreen
          snapshot={snapshot}
          connection={connection}
          online={online}
          error={error}
          onSearch={() => setScreen('search')}
          onMine={() => setScreen('mine')}
          onVote={vote}
          onWithdraw={withdraw}
        />
      )}
      {toast !== null && <Toast toast={toast} onDismiss={() => setToast(null)} />}
    </>
  )
}
