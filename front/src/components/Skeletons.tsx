export function QueueSkeleton() {
  return (
    <div aria-busy="true" aria-label="Chargement de la file">
      <div className="current">
        <div className="current__top">
          <div className="skeleton current__cover" />
          <div className="current__text" style={{ flex: 1, gap: 10 }}>
            <div className="skeleton skeleton--line" style={{ width: '40%' }} />
            <div className="skeleton skeleton--line" style={{ width: '80%', height: 20 }} />
            <div className="skeleton skeleton--line" style={{ width: '55%' }} />
          </div>
        </div>
        <div className="skeleton" style={{ height: 4 }} />
      </div>
      {Array.from({ length: 6 }, (_, index) => (
        <div key={index} className="row">
          <span className="row__rank" />
          <div className="skeleton cover" />
          <div className="row__body" style={{ gap: 8 }}>
            <div className="skeleton skeleton--line" style={{ width: `${70 - index * 5}%` }} />
            <div className="skeleton skeleton--line" style={{ width: '40%' }} />
          </div>
        </div>
      ))}
    </div>
  )
}

export function ResultsSkeleton() {
  return (
    <div aria-busy="true" aria-label="Recherche en cours">
      {Array.from({ length: 6 }, (_, index) => (
        <div key={index} className="row row--search">
          <div className="skeleton cover" />
          <div className="row__body" style={{ gap: 8 }}>
            <div className="skeleton skeleton--line" style={{ width: `${75 - index * 6}%` }} />
            <div className="skeleton skeleton--line" style={{ width: '35%' }} />
          </div>
        </div>
      ))}
    </div>
  )
}
