import type { EventStatus } from '../api/types.ts'

export const STATUS_LABEL: Record<EventStatus, string> = {
  draft: 'Brouillon',
  live: 'En direct',
  paused: 'En pause',
  ended: 'Terminée',
}

/** Error codes set by App\Service\PlaybackSynchronizer. */
export const PLAYBACK_ERRORS: Record<string, { title: string; text: string }> = {
  spotify_disconnected: {
    title: 'Spotify est déconnecté',
    text: 'Reconnecte ton compte depuis la liste des soirées.',
  },
  no_device: {
    title: 'Aucun appareil Spotify actif',
    text: "Lance un titre dans l'app Spotify (téléphone, ordinateur, enceinte) : la file prendra le relais.",
  },
  premium_required: {
    title: 'Spotify Premium requis',
    text: 'Le contrôle de la lecture est réservé aux comptes Premium.',
  },
  spotify_error: {
    title: 'Spotify ne répond pas',
    text: 'La lecture reprendra toute seule dès que Spotify répond à nouveau.',
  },
}

export function longDate(isoDate: string): string {
  return new Date(`${isoDate}T12:00:00`).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}
