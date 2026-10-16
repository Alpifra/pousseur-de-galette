import type { PersonalSnapshot, QueueItemView } from '../api/types.ts'

export interface Estimate {
  rank: number
  etaMs: number
}

/**
 * Where a brand new proposal would land: it starts with its author's upvote,
 * so it sits behind every pinned or equally voted track (see QueueManager).
 */
export function estimate(snapshot: PersonalSnapshot): Estimate {
  const before: QueueItemView[] = []
  for (const item of snapshot.queue) {
    if (item.status === 'pending' && !item.pinned && item.score < 1) {
      break
    }
    before.push(item)
  }

  const last = before.at(-1)
  const etaMs =
    last !== undefined
      ? (last.etaMs ?? 0) + last.track.durationMs
      : Math.max(0, (snapshot.current?.durationMs ?? 0) - (snapshot.current?.progressMs ?? 0))

  return { rank: before.length + 1, etaMs }
}
