export function SpotifyLink({ href }: { href: string }) {
  return (
    <a className="spotify-link" href={href} target="_blank" rel="noreferrer">
      Écouter sur Spotify
    </a>
  )
}
