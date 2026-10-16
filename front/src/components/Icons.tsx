interface IconProps {
  size?: number
  filled?: boolean
}

function stroke(size: number) {
  return {
    width: size,
    height: size,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 2.4,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    'aria-hidden': true,
  }
}

export function ThumbsUp({ size = 22, filled = false }: IconProps) {
  return (
    <svg {...stroke(size)} strokeWidth={2} fill={filled ? 'currentColor' : 'none'}>
      <path d="M7 10v12" />
      <path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z" />
    </svg>
  )
}

export function ThumbsDown({ size = 22, filled = false }: IconProps) {
  return (
    <svg {...stroke(size)} strokeWidth={2} fill={filled ? 'currentColor' : 'none'}>
      <path d="M17 14V2" />
      <path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z" />
    </svg>
  )
}

export function ArrowUp({ size = 20 }: IconProps) {
  return (
    <svg {...stroke(size)} strokeWidth={2.6}>
      <path d="M12 19V5" />
      <path d="m5 12 7-7 7 7" />
    </svg>
  )
}

export function SearchIcon({ size = 22 }: IconProps) {
  return (
    <svg {...stroke(size)}>
      <circle cx="11" cy="11" r="8" />
      <path d="m21 21-4.3-4.3" />
    </svg>
  )
}

export function ChevronLeft({ size = 24 }: IconProps) {
  return (
    <svg {...stroke(size)}>
      <path d="m15 18-6-6 6-6" />
    </svg>
  )
}

export function Cross({ size = 18 }: IconProps) {
  return (
    <svg {...stroke(size)}>
      <path d="M18 6 6 18" />
      <path d="m6 6 12 12" />
    </svg>
  )
}

export function Lock({ size = 12 }: IconProps) {
  return (
    <svg {...stroke(size)} strokeWidth={2.5}>
      <rect width="18" height="11" x="3" y="11" rx="2" />
      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
    </svg>
  )
}

export function Pin({ size = 10 }: IconProps) {
  return (
    <svg {...stroke(size)} strokeWidth={2.8}>
      <path d="M12 17v5" />
      <path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z" />
    </svg>
  )
}

export function Clock({ size = 14 }: IconProps) {
  return (
    <svg {...stroke(size)}>
      <circle cx="12" cy="12" r="10" />
      <path d="M12 6v6l4 2" />
    </svg>
  )
}

export function Ban({ size = 14 }: IconProps) {
  return (
    <svg {...stroke(size)}>
      <circle cx="12" cy="12" r="10" />
      <path d="m4.9 4.9 14.2 14.2" />
    </svg>
  )
}

export function Check({ size = 16 }: IconProps) {
  return (
    <svg {...stroke(size)} strokeWidth={2.6}>
      <path d="M20 6 9 17l-5-5" />
    </svg>
  )
}

export function Warning({ size = 20 }: IconProps) {
  return (
    <svg {...stroke(size)}>
      <path d="M12 9v4" />
      <path d="M12 17h.01" />
      <path d="M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0" />
    </svg>
  )
}

/** App mark: a vinyl record being pushed up. */
export function Logo({ size = 32 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" aria-hidden="true">
      <circle cx="24" cy="24" r="22" fill="var(--accent)" />
      <circle cx="24" cy="24" r="16.5" fill="none" stroke="#121212" strokeOpacity=".28" strokeWidth="1.5" />
      <circle cx="24" cy="24" r="12" fill="none" stroke="#121212" strokeOpacity=".28" strokeWidth="1.5" />
      <circle cx="24" cy="24" r="7.5" fill="#121212" />
      <path
        d="M24 27.5v-7M20.8 23.2 24 20l3.2 3.2"
        stroke="var(--accent)"
        strokeWidth="2"
        fill="none"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}
