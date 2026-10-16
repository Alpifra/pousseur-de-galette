import { useEffect, useRef, useState } from 'react'
import type { RealtimeMessage } from '../api/types.ts'

export type ConnectionState = 'connecting' | 'online' | 'offline'

const RETRY_DELAYS_MS = [1_000, 2_000, 5_000, 10_000]
/** Mercure 0.20 renamed the subscribe parameter: which one the hub answers is probed on the first error. */
const TOPIC_PARAMS = ['match', 'topic'] as const

interface Options {
  hub: string | null
  topic: string | null
  onMessage: (message: RealtimeMessage) => void
  /** Called on every (re)connection: the stream only carries changes, the state has to be refetched. */
  onResync: () => void
}

/**
 * Mercure subscription. EventSource reconnects on its own, but a hub that
 * answers an error closes the stream for good, so retries are handled here.
 */
export function useRealtime({ hub, topic, onMessage, onResync }: Options): ConnectionState {
  const [state, setState] = useState<ConnectionState>('connecting')
  const handlers = useRef({ onMessage, onResync })

  useEffect(() => {
    handlers.current = { onMessage, onResync }
  })

  useEffect(() => {
    if (!hub || !topic) {
      return
    }

    let source: EventSource | null = null
    let retry: number | undefined
    let attempt = 0
    let param = 0
    let probed = false
    let closed = false

    const connect = () => {
      let opened = false
      source = new EventSource(`${hub}?${TOPIC_PARAMS[param]}=${encodeURIComponent(topic)}`)

      source.onopen = () => {
        attempt = 0
        opened = true
        setState('online')
        handlers.current.onResync()
      }

      source.onmessage = (event: MessageEvent<string>) => {
        try {
          handlers.current.onMessage(JSON.parse(event.data) as RealtimeMessage)
        } catch {
          handlers.current.onResync()
        }
      }

      source.onerror = () => {
        source?.close()
        if (closed) {
          return
        }
        setState('offline')
        let delay = RETRY_DELAYS_MS[Math.min(attempt, RETRY_DELAYS_MS.length - 1)]
        if (!opened && !probed) {
          probed = true
          param = 1
          delay = 0
        } else {
          attempt += 1
        }
        retry = window.setTimeout(() => {
          setState('connecting')
          connect()
        }, delay)
      }
    }

    connect()

    return () => {
      closed = true
      window.clearTimeout(retry)
      source?.close()
    }
  }, [hub, topic])

  return hub && topic ? state : 'offline'
}
