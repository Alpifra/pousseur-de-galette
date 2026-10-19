/** Spotify's design guidelines require a link back to each track they supply. */
export function trackUrl(spotifyId: string): string {
  return `https://open.spotify.com/track/${spotifyId}`
}
