import { useLayoutEffect, useRef } from 'react'
import type { RefObject } from 'react'

const DURATION_MS = 320

/**
 * Slides the rows to their new position when the ranking changes: seeing the
 * queue reorder itself is the point of the app (maquette.md § 3.6).
 */
export function useFlip(containerRef: RefObject<HTMLElement | null>, key: string): void {
  const positions = useRef(new Map<string, number>())

  useLayoutEffect(() => {
    const container = containerRef.current
    if (!container) {
      return
    }

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const next = new Map<string, number>()

    for (const row of container.querySelectorAll<HTMLElement>('[data-flip-id]')) {
      const id = row.dataset.flipId ?? ''
      const top = row.offsetTop
      next.set(id, top)

      const previous = positions.current.get(id)
      if (!reduced && previous !== undefined && previous !== top) {
        row.animate([{ transform: `translateY(${previous - top}px)` }, { transform: 'none' }], {
          duration: DURATION_MS,
          easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)',
        })
      }
    }

    positions.current = next
  }, [containerRef, key])
}
