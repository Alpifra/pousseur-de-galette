import type { EventStatus, Me, PersonalSnapshot, QueueItemView, Snapshot } from '../api/types.ts'

/** The broadcast snapshot is guest-agnostic: the personal part is kept and recomputed locally. */
export function mergeSnapshot(previous: PersonalSnapshot, incoming: Snapshot): PersonalSnapshot {
  const me = previous.me
  return {
    ...incoming,
    realtime: previous.realtime,
    me: me === null ? null : { ...me, votes: keepKnownVotes(me, incoming.queue), pendingCount: countPending(me, incoming.queue) },
  }
}

export function applyPlayback(
  previous: PersonalSnapshot,
  playback: { spotifyId: string | null; progressMs: number; durationMs: number; isPlaying: boolean },
): PersonalSnapshot {
  if (previous.current === null || previous.current.spotifyId !== playback.spotifyId) {
    return previous
  }

  return {
    ...previous,
    current: { ...previous.current, ...playback, spotifyId: previous.current.spotifyId },
    updatedAt: new Date().toISOString(),
  }
}

export function applyStatus(previous: PersonalSnapshot, status: EventStatus): PersonalSnapshot {
  return { ...previous, event: { ...previous.event, status } }
}

/**
 * Applies a vote before the server answers so the queue reacts instantly, then
 * reorders it the way the API does (pinned, score, age) — ties keep their order.
 */
export function applyVote(previous: PersonalSnapshot, itemId: string, value: -1 | 0 | 1): PersonalSnapshot {
  const me = previous.me
  if (me === null) {
    return previous
  }

  const current = me.votes[itemId] ?? 0
  if (current === value) {
    return previous
  }

  const queue = previous.queue.map((item) => {
    if (item.id !== itemId) {
      return item
    }
    const upvotes = item.upvotes + (value === 1 ? 1 : 0) - (current === 1 ? 1 : 0)
    const downvotes = item.downvotes + (value === -1 ? 1 : 0) - (current === -1 ? 1 : 0)
    return { ...item, upvotes, downvotes, score: upvotes - downvotes }
  })

  const votes = { ...me.votes }
  if (value === 0) {
    delete votes[itemId]
  } else {
    votes[itemId] = value
  }

  return { ...previous, queue: rank(queue), me: { ...me, votes } }
}

/** Items that are already locked or playing stay on top: only the votable tail is sorted. */
function rank(queue: QueueItemView[]): QueueItemView[] {
  const head = queue.filter((item) => item.status !== 'pending')
  const tail = queue
    .filter((item) => item.status === 'pending')
    .map((item, index) => ({ item, index }))
    .sort((a, b) => {
      if (a.item.pinned !== b.item.pinned) {
        return a.item.pinned ? -1 : 1
      }
      if (a.item.score !== b.item.score) {
        return b.item.score - a.item.score
      }
      return a.index - b.index
    })
    .map(({ item }) => item)

  return [...head, ...tail].map((item, index) => ({ ...item, rank: index + 1 }))
}

function keepKnownVotes(me: Me, queue: QueueItemView[]): Record<string, number> {
  const known: Record<string, number> = {}
  for (const item of queue) {
    const value = me.votes[item.id]
    if (value !== undefined) {
      known[item.id] = value
    }
  }
  return known
}

function countPending(me: Me, queue: QueueItemView[]): number {
  return queue.filter((item) => item.status === 'pending' && item.proposedBy?.id === me.id).length
}
