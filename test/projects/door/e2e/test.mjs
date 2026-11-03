/**
 * Regression test for the door (OK-32): a session takes its roles from the request.
 *
 * Run: test/run-regression.sh door
 *
 * The spec plays both the proxy and the identity service. As the proxy it sends the groups header
 * that oauth2-proxy would send. As the identity service it makes an RSA key, serves the OpenID
 * configuration and the key set on port 9473 of the host (the prototype reaches it as
 * host.docker.internal, see regression.vars), and signs the bearer tokens it sends.
 *
 * Each interface of the model is reachable for one role, so the status of a GET on it says which
 * roles the session holds.
 */
import { createServer } from 'node:http';
import { generateKeyPairSync, sign } from 'node:crypto';
import { assert, baseUrl, failureCount } from '../../../spec-support/browser-spec.mjs';

const ISSUER = 'http://host.docker.internal:9473';
const AUDIENCE = 'http://door.test';

// ---- The identity service ------------------------------------------------------------------

const b64url = (data) => Buffer.from(data).toString('base64url');

function keyPair(kid) {
  const { privateKey, publicKey } = generateKeyPairSync('rsa', { modulusLength: 2048 });
  return { kid, privateKey, jwk: { ...publicKey.export({ format: 'jwk' }), kid, alg: 'RS256', use: 'sig' } };
}

function token(key, claims = {}) {
  const now = Math.floor(Date.now() / 1000);
  const payload = { iss: ISSUER, aud: AUDIENCE, iat: now, exp: now + 600, scp: ['Reader'], ...claims };
  const head = b64url(JSON.stringify({ alg: 'RS256', typ: 'JWT', kid: key.kid }));
  const body = b64url(JSON.stringify(payload));
  const signature = sign('RSA-SHA256', Buffer.from(`${head}.${body}`), key.privateKey);
  return `${head}.${body}.${b64url(signature)}`;
}

const key = keyPair('door-key-1');
const stranger = keyPair('door-key-1'); // same key id, other key: a forged token

const identityService = createServer((req, res) => {
  res.setHeader('Content-Type', 'application/json');
  if (req.url === '/.well-known/openid-configuration') {
    res.end(JSON.stringify({ issuer: ISSUER, jwks_uri: `${ISSUER}/.well-known/jwks.json` }));
  } else if (req.url === '/.well-known/jwks.json') {
    res.end(JSON.stringify({ keys: [key.jwk] }));
  } else {
    res.statusCode = 404;
    res.end('{}');
  }
});
await new Promise((resolve) => identityService.listen(9473, '0.0.0.0', resolve));

// ---- Requests to the prototype -------------------------------------------------------------

/** A browser: keeps its session cookie between requests. */
function browser() {
  let cookie = null;
  return async function request(path, headers = {}) {
    const resp = await fetch(`${baseUrl}/api/v1/${path}`, {
      headers: { ...(cookie ? { Cookie: cookie } : {}), ...headers },
    });
    const setCookie = resp.headers.get('set-cookie');
    if (setCookie) cookie = setCookie.split(';')[0];
    const text = await resp.text();
    let json = null;
    try {
      json = JSON.parse(text);
    } catch {
      json = null;
    }
    return { status: resp.status, json, text };
  };
}

/**
 * The allowed roles of the session, as the navigation bar reports them, apart from Anonymous:
 * the PrototypeContext of the compiler allows Anonymous to every session. A session has one active
 * role at a time (sessionActiveRoles is UNI); `activeRoles` lists it.
 */
async function allowedRoles(request, headers = {}) {
  const nav = await request('app/navbar', headers);
  return (nav.json?.sessionRoles ?? []).map((r) => r.id).sort();
}

async function roles(request, headers = {}) {
  return (await allowedRoles(request, headers)).filter((id) => id !== 'Anonymous');
}

async function activeRoles(request, headers = {}) {
  const nav = await request('app/navbar', headers);
  return (nav.json?.sessionRoles ?? []).filter((r) => r.active).map((r) => r.id).sort();
}

/** The interfaces of the model that the session can open. */
async function reachable(request, headers = {}) {
  const open = [];
  for (const ifc of ['PublicIfc', 'EditorIfc', 'ReaderIfc', 'AnonIfc']) {
    const r = await request(`resource/SESSION/1/${ifc}`, headers);
    if (r.status === 200) open.push(ifc);
  }
  return open;
}

const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

try {
  console.log('▶ A request that presents nothing');
  {
    const request = browser();
    const r = await activeRoles(request);
    assert(same(r, ['Anonymous']), `has the role Anonymous active (got ${JSON.stringify(r)})`);
    const allowed = await allowedRoles(request);
    assert(same(allowed, ['Anonymous']), `and no other role (got ${JSON.stringify(allowed)})`);
    const open = await reachable(request);
    assert(same(open, ['AnonIfc']), `opens only AnonIfc (got ${JSON.stringify(open)})`);
  }

  console.log('▶ Roles from the groups header');
  {
    const request = browser();
    const header = { 'X-Auth-Request-Groups': 'Editor' };
    const r = await roles(request, header);
    assert(same(r, ['Editor']), `the header Editor gives the role Editor (got ${JSON.stringify(r)})`);
    const open = await reachable(request, header);
    assert(same(open, ['PublicIfc', 'EditorIfc']), `opens PublicIfc and EditorIfc (got ${JSON.stringify(open)})`);
  }
  {
    const request = browser();
    const r = await roles(request, { 'X-Auth-Request-Groups': 'Editor, ExecEngine, SYSTEM, Hacker' });
    assert(same(r, ['Editor']), `the framework's own roles and undeclared names do not count (got ${JSON.stringify(r)})`);
  }
  {
    const request = browser();
    const both = await roles(request, { 'X-Auth-Request-Groups': 'Editor,Reader' });
    assert(same(both, ['Editor', 'Reader']), `a session gets two roles (got ${JSON.stringify(both)})`);
    const first = await activeRoles(request, { 'X-Auth-Request-Groups': 'Editor,Reader' });
    assert(same(first, ['Editor']), `of which the first is active (got ${JSON.stringify(first)})`);
    const kept = await activeRoles(request, { 'X-Auth-Request-Groups': 'Reader,Editor' });
    assert(same(kept, ['Editor']), `and an active role that stays allowed stays active (got ${JSON.stringify(kept)})`);
    const fewer = await roles(request, { 'X-Auth-Request-Groups': 'Reader' });
    assert(same(fewer, ['Reader']), `the same session loses Editor at the next request without it (got ${JSON.stringify(fewer)})`);
    const open = await reachable(request, { 'X-Auth-Request-Groups': 'Reader' });
    assert(!open.includes('EditorIfc'), `and can no longer open EditorIfc (got ${JSON.stringify(open)})`);
    const none = await allowedRoles(request);
    const noneActive = await activeRoles(request);
    assert(same(none, ['Anonymous']) && same(noneActive, ['Anonymous']),
      `and becomes Anonymous when the header is gone (got ${JSON.stringify(none)}, active ${JSON.stringify(noneActive)})`);
  }

  console.log('▶ Roles from a bearer token');
  {
    const request = browser();
    const auth = { Authorization: `Bearer ${token(key)}` };
    const r = await roles(request, auth);
    assert(same(r, ['Reader']), `a valid token gives the roles in scp (got ${JSON.stringify(r)})`);
    const open = await reachable(request, auth);
    assert(same(open, ['PublicIfc', 'ReaderIfc']), `opens PublicIfc and ReaderIfc (got ${JSON.stringify(open)})`);
    const withHeader = await roles(request, { ...auth, 'X-Auth-Request-Groups': 'Editor' });
    assert(same(withHeader, ['Reader']), `a token wins over a groups header (got ${JSON.stringify(withHeader)})`);
  }

  console.log('▶ Tokens that are refused');
  const refused = [
    ['an expired token', token(key, { exp: Math.floor(Date.now() / 1000) - 120 })],
    ['a token for another application', token(key, { aud: 'http://other.test' })],
    ['a token from another issuer', token(key, { iss: 'http://evil.test' })],
    ['a token signed with another key', token(stranger)],
    ['something that is not a token', 'not-a-token'],
  ];
  for (const [label, jwt] of refused) {
    const r = await browser()('app/navbar', { Authorization: `Bearer ${jwt}` });
    assert(r.status === 401, `${label} gives 401 (got ${r.status}: ${String(r.json?.msg ?? r.text).slice(0, 120)})`);
  }
} finally {
  identityService.close();
}

const failures = failureCount();
console.log(failures === 0 ? '\nAll door checks passed' : `\n${failures} door check(s) failed`);
process.exit(failures === 0 ? 0 : 1);
