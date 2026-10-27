import type { QueueItemView } from '../api/types.ts'
import { CoverArt } from './CoverArt.tsx'
import { Pin } from './Icons.tsx'
import { VoteButtons } from './VoteButtons.tsx'

interface Props {
  item: QueueItemView
  myVote: number
  mine: boolean
  votable: boolean
  allowDownvotes: boolean
  onVote: (value: -1 | 0 | 1) => void
  onWithdraw: () => void
}

export function QueueRow({ item, myVote, mine, votable, allowDownvotes, onVote, onWithdraw }: Props) {
  return (
    <li className={`row${mine ? ' row--mine' : ''}`} data-flip-id={item.id}>
      <span className="row__rank">{item.rank}</span>
      <CoverArt url={item.track.coverUrl} seed={item.track.spotifyId} />
      <div className="row__body">
        <span className="row__title">{item.track.name}</span>
        <span className="row__meta">{item.track.artist}</span>
        <span className="row__badges">
          {item.pinned && (
            <span className="badge badge--host">
              <Pin />
              Choix de l'hôte
            </span>
          )}
          {mine && <span className="badge badge--mine">Ta galette</span>}
          {!mine && !item.pinned && item.proposedBy !== null && <span>par {item.proposedBy.pseudo}</span>}
        </span>
      </div>
      <VoteButtons
        score={item.score}
        myVote={myVote}
        disabled={!votable}
        allowDownvotes={allowDownvotes}
        mine={mine}
        onVote={onVote}
        onWithdraw={onWithdraw}
      />
    </li>
  )
}
