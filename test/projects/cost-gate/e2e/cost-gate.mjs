// End-to-end regression for the conjunct cost gate (Ampersand issue #1692).
//
// See README.md for what this guards and why the spec writes the cost profiles
// itself instead of waiting for a compiler that emits them.
//
// Run by test/run-regression.sh (picked up as e2e/*.mjs). Env in:
//   PROTOTYPE_URL       base URL of the running prototype (e.g. http://localhost:9400)
//   PROTOTYPE_CONTAINER prototype (web) container name (e.g. reg-cost-gate-prototype)
// Exit 0 = all assertions pass, 1 = failure.

import { execFileSync } from 'node:child_process';
import { debugLoggingPhp } from '../../../shared/conjunct-parity.mjs';

const BASE = process.env.PROTOTYPE_URL || 'http://localhost:9400';
const PROTO = process.env.PROTOTYPE_CONTAINER || 'reg-cost-gate-prototype';
const DB = PROTO.replace(/-prototype$/, '-db');

const CONJUNCTS = '/var/www/backend/generics/conjuncts.json';
const PROJECT_YAML = '/var/www/backend/config/project.yaml';
const LOGGING_PHP = '/var/www/backend/config/logging.php';
const DEBUG_LOG = '/tmp/cost-gate-debug.log';

let failed = false;
const pass = (m) => console.log(`  PASS  ${m}`);
const fail = (m) => { console.log(`  FAIL  ${m}`); failed = true; };

function docker(container, ...cmd) {
  return execFileSync('docker', ['exec', container, ...cmd], { encoding: 'utf8' });
}

// Read a file from the prototype container.
const readInContainer = (path) => docker(PROTO, 'cat', path);

// Write a file in the prototype container. The content travels through the
// argument list rather than a shell redirection, so quoting cannot bite.
function writeInContainer(path, content) {
  execFileSync('docker', ['exec', '-i', PROTO, 'sh', '-c', `cat > ${path}`], { input: content });
}

function mysql(sql) {
  return docker(DB, 'mysql', '-uampersand', '-pampersand', '-N', '-e', sql, dbName).trim();
}

// The single application database (everything that is not a MariaDB system schema).
function findDbName() {
  const out = docker(
    DB, 'mysql', '-uampersand', '-pampersand', '-N', '-e',
    "SELECT schema_name FROM information_schema.schemata "
    + "WHERE schema_name NOT IN ('information_schema','mysql','performance_schema','sys');",
  ).trim();
  const names = out.split('\n').map((s) => s.trim()).filter(Boolean);
  if (names.length !== 1) {
    throw new Error(`Expected exactly one application database, found: ${names.join(', ') || '(none)'}`);
  }
  return names[0];
}

// ---------------------------------------------------------------- the scenarios

// Give every conjunct a cost profile. Those whose rules are named by `structural`
// get that class. The rest are scans over a table that does not exist, which keeps
// them on the integral route whatever the live table sizes are; or, with
// `rest = 'recursive'`, the class the gate always routes to incremental maintenance.
function setCostProfiles(structuralRuleNames, rest = 'scan') {
  const conjuncts = JSON.parse(readInContainer(CONJUNCTS));
  let marked = 0;
  for (const conj of conjuncts) {
    const rules = [...(conj.invariantRuleNames || []), ...(conj.signalRuleNames || [])];
    const isStructural = rules.some((r) => structuralRuleNames.some((n) => r.includes(n)));
    if (isStructural) marked++;
    conj.costProfile = isStructural
      ? { class: 'structural', scanTables: [] }
      : (rest === 'recursive'
        ? { class: 'recursive', scanTables: [] }
        : { class: 'scan', scanTables: ['__no_such_table__'] });
  }
  writeInContainer(CONJUNCTS, JSON.stringify(conjuncts, null, 2));
  return marked;
}

function setSettings(lines) {
  writeInContainer(PROJECT_YAML, ['settings:', ...lines.map((l) => `  ${l}`), ''].join('\n'));
}

// Reinstall, then drive the mutations. Returns the commit decisions plus the
// resulting data and violation cache, which is what the scenarios compare.
function runScenario() {
  // PROTOTYPE_URL is the address as seen from the host, so curl runs here, not in
  // the container (where the application listens on port 80 instead).
  const jar = `/tmp/cost-gate-cookies-${process.pid}.txt`;
  const curl = (...args) => execFileSync(
    'curl', ['-sS', '-c', jar, '-b', jar, ...args], { encoding: 'utf8' },
  );

  curl(`${BASE}/api/v1/admin/installer?ignoreInvariantRules=true`);

  // Mutations travel the interface path: the resource API grants its CRUD rights
  // per interface, so a booking is only editable through the session-rooted
  // interface that contains it.
  const session = JSON.parse(curl(`${BASE}/api/v1/resource/SESSION/1/Bookings`))._id_;

  const patch = (atom, path, value, op = 'replace') => {
    const body = JSON.stringify([{ op, path, value }]);
    const out = curl('-X', 'PATCH',
      `${BASE}/api/v1/resource/SESSION/1/Bookings/${session}/Bookings/${atom}`,
      '-H', 'Content-Type: application/json', '-d', body);
    let parsed;
    try { parsed = JSON.parse(out); } catch { return `unparseable: ${out.slice(0, 200)}`; }
    const invariants = parsed.notifications?.invariants ?? [];
    if (parsed.error) return `error ${parsed.error}: ${parsed.msg ?? ''}`;
    return invariants.length ? 'refused' : 'committed';
  };

  const decisions = [
    `guestName=Alice: ${patch('booking1', '/Guest_32_name', 'Alice')}`,
    `note=first: ${patch('booking1', '/Note', 'first')}`,
    `guestName=Bob: ${patch('booking1', '/Guest_32_name', 'Bob')}`,
    // Violates NoSelfFollow, which no table layout enforces. 'follows' is not
    // univalent, so the resource API wants add rather than replace.
    `selfFollow: ${patch('booking1', '/Follows', 'booking1', 'add')}`,
  ];

  return {
    decisions: decisions.join('\n'),
    // The ts_insertupdate column carries wall-clock time, which differs between
    // runs by construction; compare the model's own columns only.
    data: mysql('SELECT `Booking`, `guestName`, `note` FROM booking ORDER BY 1;'),
    cache: mysql('SELECT conjId, src, tgt FROM __conj_violation_cache__ ORDER BY conjId, src, tgt;'),
    follows: mysql('SELECT COUNT(*) FROM follows;'),
  };
}

// Everything the prototype logged since the given moment. Only messages at NOTICE
// and above appear: the FingersCrossedHandler holds DEBUG back until an error.
function logsSince(mark) {
  return execFileSync('docker', ['logs', '--since', mark, PROTO],
    { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
}
const now = () => Math.floor(Date.now() / 1000).toString();

// Wait until the backend really logs to the debug file: the bind mount can serve Apache a
// stale logging.php for a moment, and a run that started before then would log nowhere.
function waitForDebugLog() {
  for (let i = 0; i < 60; i++) {
    execFileSync('curl', ['-sS', '-o', '/dev/null', `${BASE}/api/v1/app/navbar`]);
    let size = '0';
    try { size = docker(PROTO, 'sh', '-c', `wc -c < ${DEBUG_LOG} 2>/dev/null || echo 0`).trim(); } catch { /* not there yet */ }
    if (Number(size) > 0) return true;
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 500);
  }
  return false;
}

// The number of conjuncts the delta protocol maintained, summed over the close summaries
// in the debug log ("Delta conjunct maintenance ('on'): N delta-maintained, ...").
function deltaMaintained() {
  let log = '';
  try { log = readInContainer(DEBUG_LOG); } catch { return { closes: 0, maintained: 0 }; }
  const lines = log.split('\n').filter((l) => l.includes("Delta conjunct maintenance ('on'): "));
  const maintained = lines.reduce((n, l) => n + (Number.parseInt(l.split("'): ")[1] ?? '', 10) || 0), 0);
  return { closes: lines.length, maintained };
}

// ---------------------------------------------------------------- run

const dbName = findDbName();
const originalYaml = readInContainer(PROJECT_YAML);
const originalConjuncts = readInContainer(CONJUNCTS);
const originalLogging = readInContainer(LOGGING_PHP);

try {
  // 1. Baseline: the gate off, which is how every release so far behaves.
  setSettings(['transactions.costGate.enabled: false']);
  const baseline = runScenario();
  if (baseline.decisions.includes('guestName=Alice: committed')
      && baseline.decisions.includes('selfFollow: refused')) {
    pass('baseline commits the ordinary edits and refuses the self-follow');
  } else {
    fail(`baseline decisions are not as expected:\n${baseline.decisions}`);
  }

  // 2. The gate on, with honest profiles: the UNI conjuncts of relations stored on
  //    the key column are structural, everything else stays integral.
  const marked = setCostProfiles(['UNI']);
  if (marked > 0) {
    pass(`${marked} conjunct(s) marked structural`);
  } else {
    fail('no conjunct was marked structural — the model or its rule naming changed');
  }

  setSettings([
    'transactions.costGate.enabled: true',
    'transactions.costGate.skipStructural: true',
    'transactions.costGate.selfCheckRate: 0.0',
  ]);
  const gated = runScenario();

  for (const [what, a, b] of [
    ['commit decisions', baseline.decisions, gated.decisions],
    ['data', baseline.data, gated.data],
    ['violation cache', baseline.cache, gated.cache],
  ]) {
    if (a === b) {
      pass(`gate on leaves the ${what} identical to the baseline`);
    } else {
      fail(`gate on changed the ${what}:\n--- off ---\n${a}\n--- on ---\n${b}`);
    }
  }

  // That the skip route really fires is not asserted from the log: the framework
  // logs it at DEBUG, which its FingersCrossedHandler buffers away unless an error
  // occurs. Scenario 3 below shows it behaviourally instead, and does so more
  // convincingly — a violating state can only slip through if the query was skipped.

  // 3. A wrong profile: NoSelfFollow is not enforced by any table layout.
  //    Without the self-check the violating state is committed ...
  setCostProfiles(['UNI', 'NoSelfFollow']);
  const unsound = runScenario();
  if (unsound.decisions.includes('selfFollow: committed') && unsound.follows === '1') {
    pass('a wrong structural profile lets the violating state through (why the claim matters)');
  } else {
    fail(`expected the wrong profile to commit the self-follow, got:\n${unsound.decisions}`);
  }

  // ... and with the self-check on, the same mutation is caught and reported.
  setSettings([
    'transactions.costGate.enabled: true',
    'transactions.costGate.skipStructural: true',
    'transactions.costGate.selfCheckRate: 1.0',
  ]);
  const mark3 = now();
  const checked = runScenario();

  if (checked.decisions.includes('selfFollow: refused') && checked.follows === '0') {
    pass('the self-check catches the wrong profile and the rule holds again');
  } else {
    fail(`the self-check did not restore the refusal, got:\n${checked.decisions}`);
  }
  if (/Cost gate self-check failed/.test(logsSince(mark3))) {
    pass('the discrepancy is reported in the log');
  } else {
    fail('the self-check found the violation but reported nothing');
  }

  // 4 and 5. The gate together with the delta protocol. Under 'on' the gate decides which
  //    conjuncts the protocol maintains: only those it routes as incremental. The summary
  //    line of the close is logged at DEBUG, so these two runs log to a file.
  writeInContainer(LOGGING_PHP, debugLoggingPhp(DEBUG_LOG, 'test/projects/cost-gate/e2e/cost-gate.mjs'));
  if (waitForDebugLog()) {
    pass('the backend logs at DEBUG to a file for the next two runs');
  } else {
    fail('the temporary logging.php did not become effective');
  }
  const unitedSettings = [
    'transactions.costGate.enabled: true',
    'transactions.costGate.skipStructural: true',
    'transactions.costGate.selfCheckRate: 0.0',
    "transactions.deltaConjunctMaintenance: 'on'",
  ];
  for (const [rest, expectation] of [['scan', 'none'], ['recursive', 'some']]) {
    setCostProfiles(['UNI'], rest);
    setSettings(unitedSettings);
    docker(PROTO, 'sh', '-c', `: > ${DEBUG_LOG}`);
    const united = runScenario();
    const { closes, maintained } = deltaMaintained();
    const label = `gate on with the delta protocol, other conjuncts '${rest}'`;
    if (closes > 0) {
      pass(`${label}: the delta path ran (${closes} close summaries)`);
    } else {
      fail(`${label}: no close summary in the debug log, so the delta path did not run`);
    }
    if (expectation === 'none' ? maintained === 0 : maintained > 0) {
      pass(`${label}: ${maintained} conjunct evaluation(s) went through the protocol, as the routes say`);
    } else {
      fail(`${label}: expected ${expectation} through the protocol, saw ${maintained}`);
    }
    for (const [what, a, b] of [
      ['commit decisions', baseline.decisions, united.decisions],
      ['data', baseline.data, united.data],
      ['violation cache', baseline.cache, united.cache],
    ]) {
      if (a === b) {
        pass(`${label}: ${what} identical to the baseline`);
      } else {
        fail(`${label} changed the ${what}:\n--- off ---\n${a}\n--- united ---\n${b}`);
      }
    }
  }
} finally {
  writeInContainer(PROJECT_YAML, originalYaml);
  writeInContainer(CONJUNCTS, originalConjuncts);
  writeInContainer(LOGGING_PHP, originalLogging);
}

if (failed) {
  console.log('1 or more assertion(s) failed');
  process.exit(1);
}
console.log('\n==== COST GATE PASSED ====');
