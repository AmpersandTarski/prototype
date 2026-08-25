/**
 * Regression test for transactions.skipCleanConjuncts (issue #443).
 *
 * With the setting on, the transaction close keeps the in-memory result of a
 * conjunct that was evaluated in this transaction (typically by the ExecEngine's
 * last fixpoint iteration) with no mutation registered afterwards. The promise
 * of the setting is: no observable difference, only fewer evaluations.
 *
 * This spec runs one API-level scenario twice — with the setting off (default)
 * and on — and requires:
 *   1. byte-identical digests: commit decisions, invariantRulesHold, invariant
 *      messages, signal messages and final data are the same in both runs;
 *   2. inside each run: a violating edit rolls back (invariant guard), a valid
 *      edit commits, the ExecEngine repair works, and signals appear/disappear
 *      with the data (violation cache);
 *   3. the skip actually fires: the "Skip evaluation of conjunct" debug line
 *      appears in the container log during the on-run and never during the
 *      off-run.
 *
 * Run via `test/run-regression.sh skip-clean-conjuncts` (the runner prepares
 * the backend API), or against the dev stack with this project compiled:
 * node test/projects/skip-clean-conjuncts/e2e/parity.mjs
 */
import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..');
const projectYaml = resolve(repoRoot, 'backend/config/project.yaml');
const loggingPhp = resolve(repoRoot, 'backend/config/logging.php');
const baseUrl = process.env.PROTOTYPE_URL ?? 'http://localhost';
const container = process.env.PROTOTYPE_CONTAINER ?? 'prototype';

const originalYaml = readFileSync(projectYaml, 'utf8');
const originalLogging = readFileSync(loggingPhp, 'utf8');

let failures = 0;
function assert(cond, msg) {
  if (cond) {
    console.log(`  ✅ ${msg}`);
  } else {
    console.error(`  ❌ ${msg}`);
    failures++;
  }
}

// ── temporary config, restored in the finally below ─────────────────────────────

// The dev logging config buffers DEBUG lines and only dumps them on an ERROR
// (FingersCrossed). To observe the skip's debug line we log DEBUG straight to
// stdout for the duration of this spec.
const debugLogging = `<?php
// TEMPORARY test config, written by test/projects/skip-clean-conjuncts/e2e/parity.mjs
use Ampersand\\Log\\RequestIDProcessor;
use Monolog\\Handler\\StreamHandler;
use Monolog\\Logger as MonologLogger;
use Monolog\\Processor\\WebProcessor;
use Monolog\\Registry;

ini_set('error_reporting', E_ALL & ~E_NOTICE); // @phan-suppress-current-line PhanTypeMismatchArgumentInternal
ini_set("display_errors", '0');
ini_set("log_errors", '1');

$processors = [new RequestIDProcessor(), new WebProcessor(extraFields: [
    'ip' => 'REMOTE_ADDR',
    'method' => 'REQUEST_METHOD',
    'url' => 'REQUEST_URI',
])];
$handlers = [new StreamHandler('php://stdout', level: MonologLogger::DEBUG)];
foreach (['EXECENGINE', 'IO', 'API', 'APPLICATION', 'DATABASE', 'CORE', 'RULEENGINE', 'TRANSACTION', 'INTERFACING'] as $name) {
    Registry::addLogger(new MonologLogger($name, $handlers, $processors));
}
`;

// Write a config file and wait until the container sees it: the macOS bind
// mount propagates file changes with a delay.
function writeConfig(hostPath, containerPath, content) {
  writeFileSync(hostPath, content);
  const deadline = Date.now() + 15000;
  for (;;) {
    const inContainer = execSync(`docker exec ${container} cat ${containerPath}`, { encoding: 'utf8' });
    if (inContainer === content) {
      return;
    }
    if (Date.now() > deadline) {
      throw new Error(`${containerPath} did not propagate to the container`);
    }
    execSync('sleep 0.2');
  }
}

function setSettings(settings) {
  const lines = Object.entries(settings)
    .map(([k, v]) => `  ${k}: ${v}`)
    .join('\n');
  const content = `# TEMPORARY test config, written by test/projects/skip-clean-conjuncts/e2e/parity.mjs\nsettings:\n${lines}\n`;
  writeConfig(projectYaml, '/var/www/backend/config/project.yaml', content);
}

// ── container log observation ───────────────────────────────────────────────────

const SKIP_LINE = "Skip evaluation of conjunct";
function skipCount() {
  const out = execSync(`docker logs ${container} 2>&1 | grep -c "${SKIP_LINE}" || true`, { encoding: 'utf8' });
  return parseInt(out.trim(), 10) || 0;
}

// ── API access with one session per scenario run ────────────────────────────────

let cookies;
async function api(path, opts = {}) {
  const headers = { 'Content-Type': 'application/json', ...(opts.headers ?? {}) };
  const cookie = [...cookies.entries()].map(([k, v]) => `${k}=${v}`).join('; ');
  if (cookie) {
    headers.Cookie = cookie;
  }
  const resp = await fetch(`${baseUrl}/api/v1/${path}`, { ...opts, headers });
  for (const sc of resp.headers.getSetCookie?.() ?? []) {
    const [k, ...v] = sc.split(';')[0].split('=');
    cookies.set(k.trim(), v.join('='));
  }
  const text = await resp.text();
  let body;
  try {
    body = JSON.parse(text);
  } catch {
    throw new Error(`${path}: HTTP ${resp.status}, non-JSON body: ${text.slice(0, 300)}`);
  }
  return { status: resp.status, body };
}

async function runInstaller() {
  const r = await fetch(`${baseUrl}/api/v1/admin/installer`);
  if (!r.ok) {
    throw new Error(`installer failed: ${r.status} ${await r.text()}`);
  }
}

// Reduce a notifications object to the messages that matter for parity
function messagesOf(notifications, key) {
  return (notifications?.[key] ?? [])
    .map((n) => (typeof n === 'string' ? n : JSON.stringify(n)))
    .sort();
}

function record(step, resp) {
  return {
    step,
    status: resp.status,
    isCommitted: resp.body.isCommitted,
    invariantRulesHold: resp.body.invariantRulesHold,
    invariants: messagesOf(resp.body.notifications, 'invariants'),
    signals: messagesOf(resp.body.notifications, 'signals'),
  };
}

// Create a fresh session (the role activation request carries the
// session-creation transaction) and return how many skip lines it produced.
async function sessionProbe() {
  const before = skipCount();
  cookies = new Map();
  await api('app/roles', {
    method: 'PATCH',
    body: JSON.stringify([{ id: 'Anonymous', active: true }]),
  });
  return skipCount() - before;
}

// The content poll in writeConfig proves that `docker exec cat` sees the new
// project.yaml, but Apache's PHP can still read a stale version for a short
// while (macOS bind mount). So after flipping the setting, wait until the
// backend's behavior matches it: a fresh session's transaction close skips
// conjuncts exactly when the setting is on.
async function waitForSettingEffective(on) {
  const deadline = Date.now() + 20000;
  for (;;) {
    const skips = await sessionProbe();
    if (on ? skips > 0 : skips === 0) {
      return;
    }
    if (Date.now() > deadline) {
      throw new Error(`skipCleanConjuncts=${on} did not become effective (last probe: ${skips} skips)`);
    }
    execSync('sleep 0.5');
  }
}

// The scenario. Returns { digest, records } where digest is a normalized JSON
// string that must be identical with the setting off and on.
async function scenario(label) {
  cookies = new Map(); // fresh session
  const records = [];

  // The Overview interface is FOR Anonymous; activate that role in this session
  // (a fresh session activates roles per the compiled PrototypeContext, which
  // need not include Anonymous).
  await api('app/roles', {
    method: 'PATCH',
    body: JSON.stringify([{ id: 'Anonymous', active: true }]),
  });

  // Discover the interface content and its field keys from a real response,
  // instead of assuming the API's field names (they are escaped labels).
  const overview = await api('resource/SESSION/1/Overview');
  const sessionAtom = overview.body._id_;
  const bookingsKey = Object.keys(overview.body).find((k) => !k.startsWith('_') && Array.isArray(overview.body[k]));
  if (!bookingsKey) {
    throw new Error(`no list field in Overview; keys: ${Object.keys(overview.body)}`);
  }
  const listPath = `${overview.body._path_}/${bookingsKey}`;
  const rows = () => api(`resource/SESSION/1/Overview`).then((r) => r.body[bookingsKey]);
  let bookings = overview.body[bookingsKey];
  const row0 = bookings[0];
  const guestKey = Object.keys(row0).find((k) => k.includes('Guest'));
  const confKey = Object.keys(row0).find((k) => k.includes('Confirmed'));
  if (!guestKey || !confKey) {
    throw new Error(`field keys not found in row; keys: ${Object.keys(row0)}`);
  }
  const pathOf = (id) => bookings.find((r) => r._id_ === id)._path_;
  records.push({ step: 'initial list', ids: bookings.map((r) => r._id_).sort(), keys: [guestKey, confKey] });

  // 1. Valid edit: name booking1 → must commit; its "needs a name" signal must clear
  let r = await api(pathOf('booking1'), {
    method: 'PATCH',
    body: JSON.stringify([{ op: 'replace', path: guestKey, value: 'Alice' }]),
  });
  records.push(record('name booking1', r));
  assert(r.body.isCommitted === true, `[${label}] naming booking1 commits`);
  assert(!r.body.notifications.signals.some((s) => JSON.stringify(s).includes('booking1')),
    `[${label}] booking1 signal cleared after naming`);

  // 2. Valid edit on a named booking: confirm booking1 → must commit
  r = await api(pathOf('booking1'), {
    method: 'PATCH',
    body: JSON.stringify([{ op: 'replace', path: confKey, value: true }]),
  });
  records.push(record('confirm booking1', r));
  assert(r.body.isCommitted === true, `[${label}] confirming named booking1 commits`);

  // 3. Invariant violation: confirm nameless booking2 → must roll back
  r = await api(pathOf('booking2'), {
    method: 'PATCH',
    body: JSON.stringify([{ op: 'replace', path: confKey, value: true }]),
  });
  records.push(record('confirm booking2 (violates)', r));
  assert(r.body.isCommitted === false && r.body.invariantRulesHold === false,
    `[${label}] confirming nameless booking2 is rejected`);
  assert(JSON.stringify(r.body.notifications.invariants).includes('booking2'),
    `[${label}] invariant message names booking2`);

  // 4. The rollback is real: booking2 is not confirmed in the database
  bookings = await rows();
  const b2 = bookings.find((row) => row._id_ === 'booking2');
  records.push({ step: 'after rollback', booking2Confirmed: b2[confKey] });
  assert(b2[confKey] === false || b2[confKey] == null, `[${label}] booking2 stays unconfirmed after rollback`);

  // 5. Create a booking → must commit, ExecEngine adds it to the session list,
  //    and its "needs a name" signal appears (violation cache gets the new row)
  r = await api(listPath, {
    method: 'POST',
    body: JSON.stringify({}),
  });
  const newId = r.body.content?._id_;
  records.push(record('create booking', r));
  assert(r.body.isCommitted === true && typeof newId === 'string', `[${label}] creating a booking commits`);
  bookings = await rows();
  records.push({ step: 'final list', ids: bookings.map((row) => row._id_).sort() });
  assert(bookings.some((row) => row._id_ === newId), `[${label}] new booking appears in the session list`);
  assert(r.body.notifications.signals.some((s) => JSON.stringify(s).includes(newId)),
    `[${label}] new booking raises the needs-a-name signal`);

  // Normalize what differs per run by construction: the generated atom id and
  // the session atom id (it can appear in _path_-like strings inside signals)
  const digest = JSON.stringify(records, null, 1)
    .replaceAll(newId, '«new-booking»')
    .replaceAll(sessionAtom, '«session»');
  return digest;
}

// ── main ────────────────────────────────────────────────────────────────────────

try {
  console.log('▶ Enabling DEBUG logging to stdout (temporary logging.php)');
  writeConfig(loggingPhp, '/var/www/backend/config/logging.php', debugLogging);

  console.log('\n▶ Phase 1: transactions.skipCleanConjuncts off (default)');
  setSettings({});
  await runInstaller();
  await waitForSettingEffective(false);
  const skipsBeforeOff = skipCount();
  const digestOff = await scenario('off');
  const skipsDuringOff = skipCount() - skipsBeforeOff;
  assert(skipsDuringOff === 0, `no conjunct evaluation skipped with the setting off (saw ${skipsDuringOff})`);

  console.log('\n▶ Phase 2: transactions.skipCleanConjuncts on');
  setSettings({ 'transactions.skipCleanConjuncts': true });
  await runInstaller();
  await waitForSettingEffective(true);
  const skipsBeforeOn = skipCount();
  const digestOn = await scenario('on');
  const skipsDuringOn = skipCount() - skipsBeforeOn;
  assert(skipsDuringOn > 0, `the skip fires with the setting on (saw ${skipsDuringOn} skipped evaluations)`);

  console.log('\n▶ Parity: off-digest versus on-digest');
  assert(digestOff === digestOn, 'digests are identical with the setting off and on');
  if (digestOff !== digestOn) {
    console.error('--- digest off ---\n' + digestOff);
    console.error('--- digest on ----\n' + digestOn);
  }
} catch (e) {
  console.error(`  ❌ ${e.message}`);
  failures++;
} finally {
  // Leave the working copy as found
  writeFileSync(projectYaml, originalYaml);
  writeFileSync(loggingPhp, originalLogging);
}

process.exit(failures === 0 ? 0 : 1);
