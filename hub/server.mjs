// Minimal Mercure-compatible hub: just what the app uses (public topics, anonymous
// subscribers, HS256 publisher JWT). Exists because the Infomaniak hosting can only run
// Node.js sites, not the Mercure binary. No history: on (re)connection the front refetches.
import { createHmac, randomUUID, timingSafeEqual } from 'node:crypto'
import { createServer } from 'node:http'
import { pathToFileURL } from 'node:url'

export const HUB_PATH = '/.well-known/mercure'
const MAX_BODY_BYTES = 1024 * 1024

export function createHub({ publisherJwtKey, corsOrigins = [], heartbeatMs = 15_000 }) {
  if (!publisherJwtKey) {
    throw new Error('The publisher JWT key is required')
  }

  const subscribers = new Set()

  // Proxies close idle connections: a comment line keeps the streams alive
  const heartbeat = setInterval(() => {
    for (const subscriber of subscribers) {
      subscriber.res.write(':\n\n')
    }
  }, heartbeatMs)
  heartbeat.unref()

  const server = createServer((req, res) => {
    const url = new URL(req.url ?? '/', 'http://hub')

    if (url.pathname === '/') {
      return send(res, 200, 'ok')
    }
    if (url.pathname !== HUB_PATH) {
      return send(res, 404, 'Not Found')
    }

    allowOrigin(req, res, corsOrigins)

    switch (req.method) {
      case 'GET':
        return subscribe(req, res, url)
      case 'POST':
        return publish(req, res)
      case 'OPTIONS':
        res.setHeader('Access-Control-Allow-Methods', 'GET, POST')
        res.setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Last-Event-ID')
        return send(res, 204)
      default:
        res.setHeader('Allow', 'GET, POST, OPTIONS')
        return send(res, 405, 'Method Not Allowed')
    }
  })

  // Mercure 0.20 subscribes with "match", older hubs with "topic": both are accepted
  function subscribe(req, res, url) {
    const selectors = [...url.searchParams.getAll('match'), ...url.searchParams.getAll('topic')]
    if (selectors.length === 0) {
      return send(res, 400, 'Missing "match" parameter')
    }

    res.writeHead(200, {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache, no-transform',
      Connection: 'keep-alive',
      'X-Accel-Buffering': 'no',
    })
    // Flushes the headers right away so that EventSource fires "open"
    res.write(':\n\n')

    const subscriber = { res, selectors: new Set(selectors) }
    subscribers.add(subscriber)
    req.on('close', () => subscribers.delete(subscriber))
  }

  async function publish(req, res) {
    const claims = verifyJwt(bearerToken(req), publisherJwtKey)
    if (!claims) {
      return send(res, 401, 'Unauthorized')
    }

    let body
    try {
      body = new URLSearchParams(await readBody(req))
    } catch {
      return send(res, 413, 'Payload Too Large')
    }

    const topics = body.getAll('topic')
    if (topics.length === 0) {
      return send(res, 400, 'Missing "topic" parameter')
    }

    const allowed = publishSelectors(claims)
    if (!topics.every((topic) => allowed.includes('*') || allowed.includes(topic))) {
      return send(res, 403, 'Forbidden')
    }

    const id = body.get('id') || `urn:uuid:${randomUUID()}`
    // Every subscriber is anonymous: a private update has nobody to go to
    if (!body.has('private')) {
      const message = formatEvent(id, body.get('type'), body.get('retry'), body.get('data') ?? '')
      for (const subscriber of subscribers) {
        if (topics.some((topic) => subscriber.selectors.has('*') || subscriber.selectors.has(topic))) {
          subscriber.res.write(message)
        }
      }
    }

    send(res, 200, id)
  }

  return {
    server,
    subscriberCount: () => subscribers.size,
    close() {
      clearInterval(heartbeat)
      for (const subscriber of subscribers) {
        subscriber.res.end()
      }
      subscribers.clear()
      return new Promise((resolve) => server.close(() => resolve()))
    },
  }
}

function send(res, status, body = '') {
  res.writeHead(status, { 'Content-Type': 'text/plain; charset=utf-8' })
  res.end(body)
}

function allowOrigin(req, res, corsOrigins) {
  const origin = req.headers.origin
  if (origin && (corsOrigins.includes('*') || corsOrigins.includes(origin))) {
    res.setHeader('Access-Control-Allow-Origin', origin)
    res.setHeader('Vary', 'Origin')
  }
}

function bearerToken(req) {
  const match = /^Bearer\s+(\S+)$/i.exec(req.headers.authorization ?? '')
  return match?.[1] ?? null
}

// symfony/mercure sends the Mercure 0.20 "authorization_details" claim, older publishers "mercure.publish"
function publishSelectors(claims) {
  const selectors = Array.isArray(claims.mercure?.publish) ? [...claims.mercure.publish] : []

  for (const detail of Array.isArray(claims.authorization_details) ? claims.authorization_details : []) {
    if (detail?.type !== 'https://mercure.rocks/authorization-detail' || !detail.actions?.includes('publish')) {
      continue
    }
    for (const topic of Array.isArray(detail.topics) ? detail.topics : []) {
      const selector = typeof topic === 'string' ? topic : (topic?.match ?? topic?.topic)
      if (typeof selector === 'string') {
        selectors.push(selector)
      }
    }
  }

  return selectors
}

function verifyJwt(token, key) {
  const parts = token?.split('.') ?? []
  if (parts.length !== 3) {
    return null
  }

  try {
    const [header, payload, signature] = parts
    if (JSON.parse(base64UrlDecode(header)).alg !== 'HS256') {
      return null
    }

    const expected = createHmac('sha256', key).update(`${header}.${payload}`).digest()
    const actual = Buffer.from(signature, 'base64url')
    if (actual.length !== expected.length || !timingSafeEqual(actual, expected)) {
      return null
    }

    const claims = JSON.parse(base64UrlDecode(payload))
    if (typeof claims.exp === 'number' && claims.exp * 1000 < Date.now()) {
      return null
    }

    return claims
  } catch {
    return null
  }
}

function base64UrlDecode(value) {
  return Buffer.from(value, 'base64url').toString('utf8')
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = []
    let size = 0
    req.on('data', (chunk) => {
      size += chunk.length
      if (size > MAX_BODY_BYTES) {
        reject(new Error('Body too large'))
        req.destroy()
        return
      }
      chunks.push(chunk)
    })
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')))
    req.on('error', reject)
  })
}

function formatEvent(id, type, retry, data) {
  let message = `id: ${id}\n`
  if (type) {
    message += `event: ${type}\n`
  }
  if (retry && /^\d+$/.test(retry)) {
    message += `retry: ${retry}\n`
  }
  for (const line of data.split(/\r\n|\r|\n/)) {
    message += `data: ${line}\n`
  }
  return `${message}\n`
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const hub = createHub({
    publisherJwtKey: process.env.MERCURE_PUBLISHER_JWT_KEY,
    corsOrigins: (process.env.MERCURE_CORS_ORIGINS ?? '').split(/\s+/).filter(Boolean),
  })
  const port = Number(process.env.PORT ?? 3000)
  hub.server.listen(port, () => console.log(`Mercure hub listening on :${port}`))

  for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => hub.close().then(() => process.exit(0)))
  }
}
