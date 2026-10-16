import { Logo } from '../components/Icons.tsx'

interface Props {
  title: string
  text: string
  action?: { label: string; onClick: () => void }
}

export function BlockedScreen({ title, text, action }: Props) {
  return (
    <div className="screen">
      <div className="blocked">
        <Logo size={64} />
        <h1 className="blocked__title">{title}</h1>
        <p className="empty__text">{text}</p>
        {action && (
          <button type="button" className="btn btn--primary" style={{ padding: '0 28px' }} onClick={action.onClick}>
            {action.label}
          </button>
        )}
      </div>
    </div>
  )
}
