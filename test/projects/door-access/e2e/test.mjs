// Regression test (OK-34): access from all allowed roles (rbac.accessibleInterfacesIfcId = Access), with the door on.
import { createServer } from 'node:http';
import { generateKeyPairSync, sign } from 'node:crypto';
import { assert, baseUrl, failureCount } from '../../../spec-support/browser-spec.mjs';

const ISSUER = 'http://host.docker.internal:9473';
const AUDIENCE = 'http://door.test';
const b64url = (data) => Buffer.from(data).toString('base64url');
const { privateKey, publicKey } = generateKeyPairSync('rsa', { modulusLength: 2048 });
const jwk = { ...publicKey.export({ format: 'jwk' }), kid: 'k1', alg: 'RS256', use: 'sig' };
function token(scp) {
  const now = Math.floor(Date.now() / 1000);
  const head = b64url(JSON.stringify({ alg: 'RS256', typ: 'JWT', kid: 'k1' }));
  const body = b64url(JSON.stringify({ iss: ISSUER, aud: AUDIENCE, iat: now, exp: now + 600, scp }));
  return `${head}.${body}.${b64url(sign('RSA-SHA256', Buffer.from(`${head}.${body}`), privateKey))}`;
}
const server = createServer((req, res) => {
  res.setHeader('Content-Type', 'application/json');
  if (req.url === '/.well-known/openid-configuration') res.end(JSON.stringify({ issuer: ISSUER, jwks_uri: `${ISSUER}/.well-known/jwks.json` }));
  else if (req.url === '/.well-known/jwks.json') res.end(JSON.stringify({ keys: [jwk] }));
  else { res.statusCode = 404; res.end('{}'); }
});
await new Promise((r) => server.listen(9473, '0.0.0.0', r));

async function reachable(headers = {}) {
  const open = [];
  for (const ifc of ['PublicIfc', 'EditorIfc', 'ReaderIfc', 'StampIfc', 'AnonIfc']) {
    const r = await fetch(`${baseUrl}/api/v1/resource/SESSION/1/${ifc}`, { headers });
    if (r.status === 200) open.push(ifc);
  }
  return open;
}
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
const check = async (label, headers, expected) => {
  const open = await reachable(headers);
  assert(same(open, expected), `${label}: ${JSON.stringify(expected)} (got ${JSON.stringify(open)})`);
};

try {
  await check('no header', {}, ['AnonIfc']);
  await check('header Editor', { 'X-Auth-Request-Groups': 'Editor' }, ['PublicIfc', 'EditorIfc', 'AnonIfc']);
  await check('header Editor,Reader opens both without switching', { 'X-Auth-Request-Groups': 'Editor,Reader' }, ['PublicIfc', 'EditorIfc', 'ReaderIfc', 'AnonIfc']);
  await check('token Stampreporter: its interface, no Any', { Authorization: `Bearer ${token(['Stampreporter'])}` }, ['StampIfc', 'AnonIfc']);
  await check('token Reader', { Authorization: `Bearer ${token(['Reader'])}` }, ['PublicIfc', 'ReaderIfc', 'AnonIfc']);
} finally {
  server.close();
}
process.exit(failureCount() === 0 ? 0 : 1);
