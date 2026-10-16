import { useState } from 'react'
import { score as formatScore } from '../lib/format.ts'
import { ThumbsDown, ThumbsUp } from './Icons.tsx'

interface Props {
  score: number
  myVote: number
  disabled: boolean
  allowDownvotes: boolean
  onVote: (value: -1 | 0 | 1) => void
}

export function VoteButtons({ score, myVote, disabled, allowDownvotes, onVote }: Props) {
  const [bumped, setBumped] = useState<'up' | 'down' | null>(null)

  const cast = (value: -1 | 1) => {
    setBumped(value === 1 ? 'up' : 'down')
    onVote(myVote === value ? 0 : value)
  }

  return (
    <div className="votes">
      <button
        type="button"
        className={`vote vote--up${bumped === 'up' ? ' vote--bump' : ''}`}
        aria-label="Voter pour"
        aria-pressed={myVote === 1}
        disabled={disabled}
        onClick={() => cast(1)}
        onAnimationEnd={() => setBumped(null)}
      >
        <ThumbsUp filled={myVote === 1} />
      </button>
      <span className={`score${myVote === 1 ? ' score--up' : ''}${myVote === -1 ? ' score--down' : ''}`}>
        {formatScore(score)}
      </span>
      {allowDownvotes && (
        <button
          type="button"
          className={`vote vote--down${bumped === 'down' ? ' vote--bump' : ''}`}
          aria-label="Voter contre"
          aria-pressed={myVote === -1}
          disabled={disabled}
          onClick={() => cast(-1)}
          onAnimationEnd={() => setBumped(null)}
        >
          <ThumbsDown filled={myVote === -1} />
        </button>
      )}
    </div>
  )
}
