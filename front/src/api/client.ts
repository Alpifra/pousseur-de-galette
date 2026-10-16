import type {
  EventSummary,
  MyProposalsResponse,
  PersonalSnapshot,
  ProposalResponse,
  SearchResponse,
  SessionResponse,
  VoteResponse,
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
  myProposals: () => request<MyProposalsResponse>('GET', '/api/my-proposals'),
}
