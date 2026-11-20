import type {
  EventSummary,
  HostDashboard,
  HostEventUpdate,
  HostMe,
  MyProposalsResponse,
  PersonalSnapshot,
  ProposalResponse,
  SearchResponse,
  SessionResponse,
  VoteResponse,
  WithdrawResponse,
} from './types.ts'

/** Mirrors App\Exception\DomainException: the code drives what the guest is told. */
export class ApiError extends Error {
  readonly code: string
  readonly status: number
  readonly context: Record<string, number | string | boolean>

  constructor(code: string, message: string, status: number, context: Record<string, number | string | boolean> = {}) {
    super(message)
    this.name = 'ApiError'
    this.code = code
    this.status = status
    this.context = context
  }

  static offline(): ApiError {
    return new ApiError('offline', 'Connexion perdue. Vérifie ton réseau.', 0)
  }
}

/** The firewall answers 401 itself, without the DomainException payload. */
export function isUnauthenticated(failure: unknown): boolean {
  return failure instanceof ApiError && (failure.status === 401 || failure.code === 'invalid_session')
}

async function request<T>(method: string, path: string, body?: unknown, signal?: AbortSignal): Promise<T> {
  let response: Response
  try {
    response = await fetch(path, {
      method,
      signal,
      headers: body === undefined ? undefined : { 'Content-Type': 'application/json' },
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') {
      throw error
    }
    throw ApiError.offline()
  }

  if (response.status === 204) {
    return undefined as T
  }

  const payload: unknown = await response.json().catch(() => null)

  if (!response.ok) {
    const error = (payload ?? {}) as { error?: string; message?: string; context?: Record<string, never> }
    throw new ApiError(
      error.error ?? 'unknown',
      error.message ?? "Une erreur est survenue, réessaie dans un instant.",
      response.status,
      error.context ?? {},
    )
  }

  return payload as T
}

export const api = {
  event: (code: string) => request<EventSummary>('GET', `/api/events/${encodeURIComponent(code)}`),
  join: (code: string, pseudo: string) =>
    request<PersonalSnapshot>('POST', `/api/events/${encodeURIComponent(code)}/join`, { pseudo }),
  session: () => request<SessionResponse>('GET', '/api/session'),
  leave: () => request<void>('DELETE', '/api/session'),
  queue: () => request<PersonalSnapshot>('GET', '/api/queue'),
  search: (query: string, signal?: AbortSignal) =>
    request<SearchResponse>('GET', `/api/search?q=${encodeURIComponent(query)}`, undefined, signal),
  propose: (spotifyId: string) => request<ProposalResponse>('POST', '/api/proposals', { spotifyId }),
  vote: (itemId: string, value: -1 | 0 | 1) =>
    request<VoteResponse>('PUT', `/api/items/${encodeURIComponent(itemId)}/vote`, { value }),
  withdraw: (itemId: string) => request<WithdrawResponse>('DELETE', `/api/items/${encodeURIComponent(itemId)}`),
  myProposals: () => request<MyProposalsResponse>('GET', '/api/my-proposals'),
}

const hostEvent = (code: string) => `/api/host/events/${encodeURIComponent(code)}`

export const hostApi = {
  login: (email: string, password: string) => request<HostMe>('POST', '/api/host/login', { email, password }),
  logout: () => request<unknown>('POST', '/api/host/logout'),
  me: () => request<HostMe>('GET', '/api/host/me'),
  createEvent: (name: string, scheduledFor: string | null) =>
    request<HostDashboard>('POST', '/api/host/events', { name, scheduledFor }),
  event: (code: string) => request<HostDashboard>('GET', hostEvent(code)),
  updateEvent: (code: string, changes: HostEventUpdate) => request<HostDashboard>('PATCH', hostEvent(code), changes),
  removeItem: (code: string, itemId: string) =>
    request<HostDashboard>('DELETE', `${hostEvent(code)}/items/${encodeURIComponent(itemId)}`),
  pinItem: (code: string, itemId: string, pinned: boolean) =>
    request<HostDashboard>('PUT', `${hostEvent(code)}/items/${encodeURIComponent(itemId)}/pin`, { pinned }),
  skip: (code: string) => request<void>('POST', `${hostEvent(code)}/skip`),
  qrCodeUrl: (code: string) => `${hostEvent(code)}/qr.svg`,
  disconnectSpotify: () => request<void>('POST', '/api/spotify/disconnect'),
  connectSpotifyUrl: '/api/spotify/connect',
}
