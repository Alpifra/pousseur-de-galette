import { Suspense, lazy } from 'react'
import App from './App.tsx'

// Guests never load the host screens
const HostApp = lazy(() => import('./HostApp.tsx'))

export default function Root() {
  if (/^\/host(\/|$)/.test(window.location.pathname)) {
    return (
      <Suspense>
        <HostApp />
      </Suspense>
    )
  }

  return <App />
}
