import type { QueueItemView } from '../api/types.ts'
import { score, waitLabel } from '../lib/format.ts'
import { CoverArt } from './CoverArt.tsx'
import { Pin, Trash } from './Icons.tsx'

interface Props {
  item: QueueItemView
  disabled: boolean
  onPin: (pinned: boolean) => void
  onRemove: () => void
}

export function HostQueueRow({ item, disabled, onPin, onRemove }: Props) {
  return (
    <li className="row host__row" data-flip-id={item.id}>
      <span className="row__rank">{item.rank}</span>
      <CoverArt url={item.track.coverUrl} seed={item.track.spotifyId} />
      <div className="row__body">
        <span className="row__title">{item.track.name}</span>
        <span className="row__meta">{item.track.artist}</span>
        <span className="row__badges">
          {item.pinned && (
            <span className="badge badge--host">
              <Pin />
              Épinglé
            </span>
          )}
          {item.proposedBy !== null && <span>par {item.proposedBy.pseudo}</span>}
          <span>· {score(item.score)}</span>
          <span>· {waitLabel(item.etaMs)}</span>
        </span>
      </div>
      <button
        type="button"
        className="vote host__pin"
        aria-pressed={item.pinned}
        aria-label={item.pinned ? 'Désépingler' : 'Épingler en tête de file'}
        title={item.pinned ? 'Désépingler' : 'Épingler en tête de file'}
        disabled={disabled}
        onClick={() => onPin(!item.pinned)}
      >
        <Pin size={20} />
      </button>
      <button
        type="button"
        className="vote vote--withdraw"
        aria-label="Retirer de la file"
        title="Retirer de la file"
        disabled={disabled}
        onClick={onRemove}
      >
        <Trash size={20} />
      </button>
    </li>
  )
}
