export type EventStatus = 'draft' | 'live' | 'paused' | 'ended'

export type QueueItemStatus = 'pending' | 'locked' | 'playing' | 'played' | 'removed' | 'rejected'

export type SearchState = 'available' | 'queued' | 'rejected' | 'replayed' | 'explicit' | 'too_long' | 'quota'

export interface TrackView {
  spotifyId: string
  name: string
  artist: string
  album: string | null
  coverUrl: string | null
  durationMs: number
  explicit: boolean
}

export interface GuestView {
  id: string
  pseudo: string
}

export interface QueueItemView {
  id: string
  rank: number
  status: QueueItemStatus
  locked: boolean
  pinned: boolean
  score: number
  upvotes: number
  downvotes: number
  etaMs: number | null
  proposedBy: GuestView | null
  track: TrackView
}

export interface CurrentTrackView {
  spotifyId: string
  name: string | null
  artist: string | null
  coverUrl: string | null
  durationMs: number
  progressMs: number
  isPlaying: boolean
  fromQueue: boolean
  proposedBy: GuestView | null
  queueItemId: string | null
}

export interface EventView {
  id: string
  name: string
  status: EventStatus
  settings: {
    allowDownvotes: boolean
    maxPendingPerGuest: number
    blockExplicit: boolean
  }
}

export interface Snapshot {
  event: EventView
  guests: number
  current: CurrentTrackView | null
  queue: QueueItemView[]
  playbackError: string | null
  updatedAt: string
}

export interface Me {
  id: string
  pseudo: string
  pendingCount: number
  maxPending: number
  votes: Record<string, number>
}

export interface PersonalSnapshot extends Snapshot {
  realtime: { hub: string | null; topic: string | null }
  me: Me | null
}

export interface EventSummary {
  id: string
  name: string
  status: EventStatus
  joinCode: string
}

export interface SessionResponse {
  guest: { id: string; pseudo: string; banned: boolean } | null
  event?: EventSummary
}

export interface SearchResult extends TrackView {
  state: SearchState
  statusLabel: string | null
  queueItemId: string | null
}

export interface SearchResponse {
  query: string
  results: SearchResult[]
  pending: { count: number; max: number }
}

export interface ProposalResponse {
  alreadyQueued: boolean
  item: QueueItemView | null
  snapshot: PersonalSnapshot
}

export interface VoteResponse {
  item: QueueItemView | null
  snapshot: PersonalSnapshot
}

export interface WithdrawResponse {
  snapshot: PersonalSnapshot
}

export interface MyProposalsResponse {
  pseudo: string
  pending: QueueItemView[]
  played: QueueItemView[]
  removed: QueueItemView[]
  nextRank: number | null
}

/** Realtime payloads, see App\Service\RealtimePublisher. */
export type RealtimeMessage =
  | ({ type: 'queue' } & Snapshot)
  | { type: 'playback'; spotifyId: string | null; progressMs: number; durationMs: number; isPlaying: boolean }
  | { type: 'status'; status: EventStatus }

export interface EventSettingsView {
  allowDownvotes: boolean
  rejectionThreshold: number
  maxPendingPerGuest: number
  replayDelayMinutes: number
  blockExplicit: boolean
  maxTrackDurationMs: number
  lockLeadMs: number
}

export interface HostEventSummary {
  id: string
  name: string
  status: EventStatus
  joinCode: string
  joinUrl: string
  scheduledFor: string | null
  createdAt: string
}

export interface HostMe {
  id: string
  email: string
  spotify: { connected: boolean; premium: boolean | null }
  events: HostEventSummary[]
}

export interface HostEventView {
  id: string
  name: string
  status: EventStatus
  joinCode: string
  joinUrl: string
  scheduledFor: string | null
  device: string | null
  settings: EventSettingsView
}

export interface HostDashboard {
  event: HostEventView
  snapshot: Snapshot
  realtime: { hub: string | null; topic: string | null }
}

export interface HostEventUpdate {
  name?: string
  scheduledFor?: string | null
  status?: EventStatus
  settings?: Partial<EventSettingsView>
}
