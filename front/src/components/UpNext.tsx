import type { QueueItemView } from '../api/types.ts'
import { CoverArt } from './CoverArt.tsx'
import { Lock } from './Icons.tsx'

export function UpNext({ item }: { item: QueueItemView }) {
  return (
    <section className="section" aria-label="À suivre">
      <span className="section__title">À suivre</span>
      <div className="row row--locked">
        <CoverArt url={item.track.coverUrl} seed={item.track.spotifyId} />
        <div className="row__body">
          <span className="row__title">{item.track.name}</span>
          <span className="row__meta">
            {item.track.artist}
            {item.proposedBy !== null && ` · par ${item.proposedBy.pseudo}`}
          </span>
        </div>
        <span className="badge badge--locked">
          <Lock />
          Verrouillé
        </span>
      </div>
    </section>
  )
}
