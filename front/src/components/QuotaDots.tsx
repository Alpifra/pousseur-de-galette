interface Props {
  count: number
  max: number
}

export function QuotaDots({ count, max }: Props) {
  return (
    <span className="quota">
      <span className="quota__dots" aria-hidden="true">
        {Array.from({ length: max }, (_, index) => (
          <span key={index} className={`quota__dot${index < count ? ' quota__dot--on' : ''}`} />
        ))}
      </span>
      {count} / {max} galettes en attente
    </span>
  )
}
