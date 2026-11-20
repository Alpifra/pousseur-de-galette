import assert from 'node:assert/strict'
import { createHmac } from 'node:crypto'
import { after, before, test } from 'node:test'
import { createHub, HUB_PATH } from './server.mjs'

const KEY = 'test-secret'
const TOPIC = 'https://pousseur-de-galette.app/events/1'
const ORIGIN = 'https://pdg.example'

let hub
let base

before(async () => {
  hub = createHub({ publisherJwtKey: KEY, corsOrigins: [ORIGIN] })
  await new Promise((resolve) => hub.server.listen(0, '127.0.0.1', resolve))
  base = `http://127.0.0.1:${hub.server.address().port}${HUB_PATH}`
})

after(() => hub.close())

function jwt(claims, key = KEY, alg = 'HS256') {
  const encode = (value) => Buffer.from(JSON.stringify(value)).toString('base64url')
  const unsigned = `${encode({ alg, typ: 'JWT' })}.${encode(claims)}`
  return `${unsigned}.${createHmac('sha256', key).update(unsigned).digest('base64url')}`
}

/** The claim format symfony/mercure sends (Mercure 0.20). */
function authorizationDetails(actions, topics) {
  return { authorization_details: [{ type: 'https://mercure.rocks/authorization-detail', actions, topics }] }
}

function publish(fields, token = jwt({ mercure: { publish: ['*'] } })) {
  const body = new URLSearchParams()
  for (const [name, value] of Object.entries(fields)) {
    for (const item of [value].flat()) {
      body.append(name, item)
    }
  }
  return fetch(base, { method: 'POST', headers: { Authorization: `Bearer ${token}` }, body })
}

async function subscribe(query, headers = {}) {
  const controller = new AbortController()
  const res = await fetch(`${base}?${query}`, { headers, signal: controller.signal })
  const reader = res.body.getReader()
  const decoder = new TextDecoder()
  let buffer = ''

  return {
    res,
    /** Next event, skipping heartbeat comments; null when nothing arrives in time. */
    async next(timeoutMs = 300) {
      const deadline = Date.now() + timeoutMs
      while (true) {
        const events = buffer.split('\n\n')
        const index = events.findIndex((event) => event.trim() !== '' && !event.startsWith(':'))
        if (index !== -1 && index < events.length - 1) {
          buffer = events.slice(index + 1).join('\n\n')
          return events[index]
        }
        const remaining = deadline - Date.now()
        if (remaining <= 0) {
          return null
        }
        const chunk = await Promise.race([
          reader.read(),
          new Promise((resolve) => setTimeout(() => resolve(null), remaining)),
        ])
        if (!chunk || chunk.done) {
          return null
        }
        buffer += decoder.decode(chunk.value, { stream: true })
      }
    },
    close: () => controller.abort(),
  }
}

test('an update reaches the subscribers of its topic, with either parameter name', async () => {
  const byMatch = await subscribe(`match=${encodeURIComponent(TOPIC)}`)
  const byTopic = await subscribe(`topic=${encodeURIComponent(TOPIC)}`)
  const other = await subscribe(`match=${encodeURIComponent(`${TOPIC}-other`)}`)

  assert.equal(byMatch.res.headers.get('content-type'), 'text/event-stream')

  const res = await publish({ topic: TOPIC, data: '{"type":"queue"}' })
  assert.equal(res.status, 200)
  const id = await res.text()
  assert.match(id, /^urn:uuid:/)

  assert.equal(await byMatch.next(), `id: ${id}\ndata: {"type":"queue"}`)
  assert.equal(await byTopic.next(), `id: ${id}\ndata: {"type":"queue"}`)
  assert.equal(await other.next(), null)

  for (const subscriber of [byMatch, byTopic, other]) subscriber.close()
})

test('multi-line data, event type and retry follow the SSE format', async () => {
  const subscriber = await subscribe(`match=${encodeURIComponent(TOPIC)}`)

  await publish({ topic: TOPIC, data: 'first\nsecond', type: 'ping', retry: '5000', id: 'custom' })

  assert.equal(await subscriber.next(), 'id: custom\nevent: ping\nretry: 5000\ndata: first\ndata: second')
  subscriber.close()
})

test('a private update is never sent to the anonymous subscribers', async () => {
  const subscriber = await subscribe(`match=${encodeURIComponent(TOPIC)}`)

  const res = await publish({ topic: TOPIC, data: 'secret', private: 'on' })

  assert.equal(res.status, 200)
  assert.equal(await subscriber.next(), null)
  subscriber.close()
})

test('a closed subscription is forgotten', async () => {
  const subscriber = await subscribe(`match=${encodeURIComponent(TOPIC)}`)
  const before = hub.subscriberCount()

  subscriber.close()
  await new Promise((resolve) => setTimeout(resolve, 50))

  assert.equal(hub.subscriberCount(), before - 1)
})

test('publishing requires a valid HS256 token allowed on the topic', async () => {
  const cases = [
    [jwt({ mercure: { publish: ['*'] } }, 'wrong-key'), 401],
    [jwt({ mercure: { publish: ['*'] } }, KEY, 'none'), 401],
    [jwt({ mercure: { publish: ['*'] }, exp: Math.floor(Date.now() / 1000) - 60 }), 401],
    ['not-a-jwt', 401],
    [jwt({ mercure: { publish: ['https://elsewhere'] } }), 403],
    [jwt({}), 403],
    [jwt({ mercure: { publish: [TOPIC] } }), 200],
    [jwt(authorizationDetails(['publish'], [{ match: '*' }])), 200],
    [jwt(authorizationDetails(['publish'], [{ match: TOPIC }])), 200],
    [jwt(authorizationDetails(['subscribe'], [{ match: '*' }])), 403],
    [jwt(authorizationDetails(['publish'], [{ match: 'https://elsewhere' }])), 403],
  ]

  for (const [token, status] of cases) {
    assert.equal((await publish({ topic: TOPIC, data: 'x' }, token)).status, status)
  }
  assert.equal((await fetch(base, { method: 'POST', body: 'topic=x' })).status, 401)
})

test('requests without a topic are rejected', async () => {
  assert.equal((await fetch(base)).status, 400)
  assert.equal((await publish({ data: 'x' })).status, 400)
})

test('only the configured origins are allowed to subscribe cross-origin', async () => {
  const allowed = await subscribe(`match=${encodeURIComponent(TOPIC)}`, { Origin: ORIGIN })
  const denied = await subscribe(`match=${encodeURIComponent(TOPIC)}`, { Origin: 'https://evil.example' })

  assert.equal(allowed.res.headers.get('access-control-allow-origin'), ORIGIN)
  assert.equal(denied.res.headers.get('access-control-allow-origin'), null)

  allowed.close()
  denied.close()
})

test('the root answers for the hosting health check, anything else is a 404', async () => {
  const root = base.replace(HUB_PATH, '/')

  assert.equal((await fetch(root)).status, 200)
  assert.equal((await fetch(`${root}nope`)).status, 404)
})
