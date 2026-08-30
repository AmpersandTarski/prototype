/**
 * Shared pieces for the transaction-close parity specs
 * (test/projects/skip-clean-conjuncts, test/projects/delta-conjunct-maintenance).
 *
 * Both specs run the same API scenario on the same model under different
 * settings and require identical digests. This module holds the scenario, the
 * cookie-aware API client, the temporary-config writers and the log counters;
 * each spec keeps its own phase logic and its own proof that a setting fired.
 *
 * Everything here is plain Node (no child_process): the specs observe the
 * backend through the API and through a DEBUG log written to a bind-mounted
 * file, never through docker.
 */
import { existsSync, readFileSync, writeFileSync } from 'node:fs';

// ── reporting ───────────────────────────────────────────────────────────────────

export function reporter() {
  let failures = 0;
  return {
    assert(cond, msg) {
      if (cond) {
        console.log(`  ✅ ${msg}`);
      } else {
        console.error(`  ❌ ${msg}`);
        failures++;
      }
    },
    fail(msg) {
      console.error(`  ❌ ${msg}`);
      failures++;
    },
    get failures() {
      return failures;
    },
  };
}

// ── temporary config ────────────────────────────────────────────────────────────

/** Content for backend/config/project.yaml with the given settings */
export function settingsYaml(settings, writtenBy) {
  const lines = Object.entries(settings)
    .map(([k, v]) => `  ${k}: ${v}`)
    .join('\n');
  return `# TEMPORARY test config, written by ${writtenBy}\nsettings:\n${lines}\n`;
}

/**
 * Content for backend/config/logging.php that logs DEBUG to a file at
 * `containerLogPath` (a path on the bind mount, so the spec can read it back).
 * The dev config buffers DEBUG lines and only dumps them on an ERROR
 * (FingersCrossed), which hides the lines the specs need.
 */
export function debugLoggingPhp(containerLogPath, writtenBy) {
  return String.raw`<?php
// TEMPORARY test config, written by ${writtenBy}
use Ampersand\Log\RequestIDProcessor;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\WebProcessor;
use Monolog\Registry;

ini_set('error_reporting', E_ALL & ~E_NOTICE); // @phan-suppress-current-line PhanTypeMismatchArgumentInternal
ini_set("display_errors", '0');
ini_set("log_errors", '1');

$processors = [new RequestIDProcessor(), new WebProcessor(extraFields: [
    'ip' => 'REMOTE_ADDR',
    'method' => 'REQUEST_METHOD',
    'url' => 'REQUEST_URI',
])];
$handlers = [
    new StreamHandler('${containerLogPath}', level: MonologLogger::DEBUG),
    new StreamHandler('php://stderr', level: MonologLogger::WARNING),
];
foreach (['EXECENGINE', 'IO', 'API', 'APPLICATION', 'DATABASE', 'CORE', 'RULEENGINE', 'TRANSACTION', 'INTERFACING'] as $name) {
    Registry::addLogger(new MonologLogger($name, $handlers, $processors));
}
`;
}

/** Number of occurrences of `needle` in the file (0 when the file does not exist) */
export function countIn(file, needle) {
  if (!existsSync(file)) {
    return 0;
  }
  return readFileSync(file, 'utf8').split(needle).length - 1;
}

/** Write a file (thin wrapper, so specs import one module) */
export function writeConfig(path, content) {
  writeFileSync(path, content);
}

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ── API client ──────────────────────────────────────────────────────────────────

/**
 * A fetch wrapper that keeps the PHP session cookie, so a run of requests
 * shares one Ampersand session. newSession() starts a fresh one.
 */
export function makeClient(baseUrl) {
  let cookies = new Map();
  return {
    newSession() {
      cookies = new Map();
    },
    async api(path, opts = {}) {
      const headers = { 'Content-Type': 'application/json', ...opts.headers };
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
    },
  };
}

export async function runInstaller(baseUrl) {
  const r = await fetch(`${baseUrl}/api/v1/admin/installer`);
  if (!r.ok) {
    throw new Error(`installer failed: ${r.status} ${await r.text()}`);
  }
}

/**
 * Wait until the backend reads the project.yaml a spec just wrote. The navbar
 * exposes `frontend.menuMode`; a spec writes a phase-specific value for it in
 * the same file as the setting under test, so seeing the canary proves the
 * backend sees that file (the macOS bind mount can serve Apache a stale copy
 * for a short while after the host wrote it).
 */
export async function waitForCanary(client, expectedMenuMode, timeoutMs = 20000) {
  const deadline = Date.now() + timeoutMs;
  for (;;) {
    client.newSession();
    const r = await client.api('app/navbar');
    if (r.body.menuMode === expectedMenuMode) {
      return;
    }
    if (Date.now() > deadline) {
      throw new Error(`project.yaml did not become effective (navbar menuMode is '${r.body.menuMode}', expected '${expectedMenuMode}')`);
    }
    await sleep(500);
  }
}

/**
 * Wait until the temporary logging.php is effective: make requests until the
 * debug log file exists and holds at least one line. Same bind-mount caveat as
 * waitForCanary; the log is the only way to observe this config.
 */
export async function waitForDebugLog(client, debugLog, timeoutMs = 20000) {
  const deadline = Date.now() + timeoutMs;
  for (;;) {
    client.newSession();
    await client.api('app/navbar');
    if (countIn(debugLog, '\n') > 0) {
      return;
    }
    if (Date.now() > deadline) {
      throw new Error(`debug log ${debugLog} did not appear (temporary logging.php not effective)`);
    }
    await sleep(500);
  }
}

// ── the scenario ────────────────────────────────────────────────────────────────

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

/**
 * Activate the Anonymous role in a fresh session. The Overview interface of
 * the model is FOR Anonymous; a fresh session activates roles per the compiled
 * PrototypeContext, which need not include Anonymous. The request carries the
 * session-creation transaction.
 */
export async function activateAnonymous(client) {
  client.newSession();
  return client.api('app/roles', {
    method: 'PATCH',
    body: JSON.stringify([{ id: 'Anonymous', active: true }]),
  });
}

/**
 * One API scenario on the booking model (test/projects/skip-clean-conjuncts/model):
 * a committing edit, an ExecEngine repair, an invariant rollback, a create, and
 * the accompanying signals. Returns a normalized JSON digest that must be
 * identical whatever transaction-close optimisation is switched on.
 */
export async function bookingScenario(client, label, assert) {
  const records = [];
  const { api } = client;
  await activateAnonymous(client);

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
  return JSON.stringify(records, null, 1)
    .replaceAll(newId, '«new-booking»')
    .replaceAll(sessionAtom, '«session»');
}
