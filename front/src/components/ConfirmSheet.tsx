import { useState } from 'react'

interface Props {
  title: string
  text: string
  confirmLabel: string
  onCancel: () => void
  onConfirm: () => Promise<void>
}

export function ConfirmSheet({ title, text, confirmLabel, onCancel, onConfirm }: Props) {
  const [loading, setLoading] = useState(false)

  return (
    <>
      <div className="overlay" onClick={onCancel} aria-hidden="true" />
      <section className="sheet" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">
        <div className="sheet__grip" aria-hidden="true" />
        <h2 id="confirm-title" className="sheet__title">
          {title}
        </h2>
        <p className="sheet__text">{text}</p>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <button
            type="button"
            className="btn btn--danger btn--block"
            disabled={loading}
            onClick={() => {
              setLoading(true)
              void onConfirm().finally(() => setLoading(false))
            }}
          >
            {loading ? <span className="spinner" /> : confirmLabel}
          </button>
          <button type="button" className="btn btn--ghost" onClick={onCancel}>
            Annuler
          </button>
        </div>
      </section>
    </>
  )
}
