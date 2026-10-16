import { useEffect, useRef, useState } from 'react'
import { ApiError, api } from '../api/client.ts'
import type { PersonalSnapshot, SearchResult } from '../api/types.ts'
import { CoverArt } from '../components/CoverArt.tsx'
import { Ban, ChevronLeft, Clock, Cross, SearchIcon, ThumbsUp, Warning } from '../components/Icons.tsx'
import { ProposalSheet } from '../components/ProposalSheet.tsx'
import { QuotaDots } from '../components/QuotaDots.tsx'
import { ResultsSkeleton } from '../components/Skeletons.tsx'
import { duration, plural } from '../lib/format.ts'
import { useDebounced } from '../lib/useDebounced.ts'

interface Props {
  snapshot: PersonalSnapshot
  onBack: () => void
  onPropose: (result: SearchResult) => Promise<void>
}

const MIN_LENGTH = 2

/** Results are kept with the query that produced them, so "still searching" is derived instead of tracked. */
interface Settled {
  query: string
  results: SearchResult[] | null
  error: string | null
}

export function SearchScreen({ snapshot, onBack, onPropose }: Props) {
  const [query, setQuery] = useState('')
  const [settled, setSettled] = useState<Settled>({ query: '', results: null, error: null })
  const [pending, setPending] = useState(() => ({
    count: snapshot.me?.pendingCount ?? 0,
    max: snapshot.me?.maxPending ?? 0,
  }))
  const [chosen, setChosen] = useState<SearchResult | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const debounced = useDebounced(query.trim(), 300)

  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  useEffect(() => {
    if (debounced.length < MIN_LENGTH) {
      return
    }

    const controller = new AbortController()
    api
      .search(debounced, controller.signal)
      .then((response) => {
        setSettled({ query: debounced, results: response.results, error: null })
        setPending(response.pending)
      })
      .catch((failure: unknown) => {
        if (failure instanceof DOMException && failure.name === 'AbortError') {
          return
        }
        setSettled({
          query: debounced,
          results: [],
          error: failure instanceof ApiError ? failure.message : 'Recherche indisponible, réessaie.',
        })
      })

    return () => controller.abort()
  }, [debounced])

  const typing = debounced.length < MIN_LENGTH
  const searching = !typing && settled.query !== debounced
  const shown = typing ? null : settled.results
  const error = typing || searching ? null : settled.error
  const quotaReached = pending.max > 0 && pending.count >= pending.max

  return (
    <div className="screen">
      <div className="search__bar">
        <button type="button" className="icon-btn" aria-label="Retour à la file" onClick={onBack}>
          <ChevronLeft />
        </button>
        <label className="search__field" htmlFor="q">
          <SearchIcon size={20} />
          <input
            id="q"
            ref={inputRef}
            className="search__input"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Titre, artiste…"
            aria-label="Rechercher un titre ou un artiste"
            autoComplete="off"
            enterKeyHint="search"
          />
          {query !== '' && (
            <button type="button" className="search__clear" aria-label="Effacer" onClick={() => setQuery('')}>
              <Cross />
            </button>
          )}
        </label>
      </div>

      <div className="search__status">
        <span>{shown === null ? 'Cherche un titre, un artiste…' : plural(shown.length, 'résultat', 'résultats')}</span>
        {snapshot.me !== null && <QuotaDots count={pending.count} max={pending.max} />}
      </div>

      {quotaReached && (
        <div className="banner banner--warning">
          <Warning />
          <div className="banner__body">
            <span className="banner__title">Quota atteint</span>
            <span className="banner__text">
              Attends qu'une de tes galettes passe pour en proposer une nouvelle. Tu peux toujours voter.
            </span>
          </div>
        </div>
      )}

      {error !== null && (
        <div className="banner banner--error">
          <Warning />
          {error}
        </div>
      )}

      {searching && shown === null ? (
        <ResultsSkeleton />
      ) : shown !== null && shown.length === 0 && error === null ? (
        <div className="empty">
          <span className="empty__title">Aucun titre trouvé</span>
          <span className="empty__text">Essaie avec le nom de l'artiste, ou vérifie l'orthographe.</span>
        </div>
      ) : (
        <ul>
          {(shown ?? []).map((result) => (
            <ResultRow key={result.spotifyId} result={result} onChoose={() => setChosen(result)} />
          ))}
        </ul>
      )}

      {chosen !== null && (
        <ProposalSheet
          result={chosen}
          snapshot={snapshot}
          onCancel={() => setChosen(null)}
          onConfirm={async () => {
            await onPropose(chosen)
            setChosen(null)
          }}
        />
      )}
    </div>
  )
}

function ResultRow({ result, onChoose }: { result: SearchResult; onChoose: () => void }) {
  const disabled = result.state !== 'available' && result.state !== 'queued'

  return (
    <li className={`row row--search${disabled ? ' row--disabled' : ''}`}>
      <CoverArt url={result.coverUrl} seed={result.spotifyId} />
      <div className="row__body">
        <span className="row__title">{result.name}</span>
        <span className="row__meta">
          {result.explicit && <span className="badge badge--explicit">E</span>} {result.artist} ·{' '}
          {duration(result.durationMs)}
        </span>
      </div>

      {result.state === 'available' && (
        <button type="button" className="row__action row__action--propose" onClick={onChoose}>
          Proposer
        </button>
      )}
      {result.state === 'queued' && (
        <button type="button" className="row__action row__action--queued" onClick={onChoose}>
          Dans la file
          <ThumbsUp size={16} />
          +1
        </button>
      )}
      {disabled && (
        <span className="row__status">
          {result.state === 'replayed' ? <Clock /> : <Ban />}
          {result.statusLabel ?? 'Indisponible'}
        </span>
      )}
    </li>
  )
}
