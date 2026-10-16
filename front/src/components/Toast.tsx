import { useEffect } from 'react'
import { CoverArt } from './CoverArt.tsx'

export interface ToastData {
  id: number
  title: string
  text?: string
  cover?: { url: string | null; seed: string }
  action?: { label: string; onClick: () => void }
  variant?: 'success' | 'error'
}

interface Props {
  toast: ToastData
  onDismiss: () => void
}

const LIFETIME_MS = 4_000

export function Toast({ toast, onDismiss }: Props) {
  useEffect(() => {
    const timer = window.setTimeout(onDismiss, LIFETIME_MS)
    return () => window.clearTimeout(timer)
  }, [toast.id, onDismiss])

  return (
    <div className={`toast${toast.variant === 'error' ? ' toast--error' : ''}`} role="status">
      {toast.cover && <CoverArt url={toast.cover.url} seed={toast.cover.seed} className="cover" />}
      <div className="toast__body">
        <span className="toast__title">{toast.title}</span>
        {toast.text !== undefined && <span className="toast__text">{toast.text}</span>}
      </div>
      {toast.action && (
        <button type="button" className="toast__action" onClick={toast.action.onClick}>
          {toast.action.label}
        </button>
      )}
    </div>
  )
}
