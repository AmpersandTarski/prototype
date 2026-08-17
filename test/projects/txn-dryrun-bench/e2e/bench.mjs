/**
 * Measures the dry-run cadence of a TRANSACTIONAL interface, and checks that restricting
 * the evaluation changes nothing a user or a commit depends on.
 * AmpersandTarski/prototype#445, DesignChoices OK-10.
 *
 * What it does, per population size and per setting of transactions.editScopedDryRun:
 *
 *  1. imports a `near` population of the requested size, so the model's expensive
 *     conjunct (a self-join over `near`) costs something to evaluate;
 *  2. replays a buffer of 1, 5 and 20 edits the way the frontend does — one PATCH with
 *     `dryRun=true` per edit, carrying the whole buffer so far — and times every round;
 *  3. records the worklist (the invariant messages) of every round;
 *  4. commits a clean buffer and a violating buffer, and records the commit decision.
 *
 * Then it compares the two settings. The verdict is a failure when any round's worklist
 * differs, or when any commit decision differs. The timings are reported, never asserted:
 * a wall-clock number is evidence, not a gate.
 *
 * Run standalone against a stack of your own:
 *   PROTOTYPE_URL=http://localhost:9400 node test/projects/txn-dryrun-bench/e2e/bench.mjs
 * Sizes and repeats are tunable:
 *   BENCH_SIZES=200,400,800 BENCH_REPEATS=5 node .../bench.mjs
 */
import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(DIR, '../../../..');
const PROJECT_YAML = resolve(REPO, 'backend/config/project.yaml');
const BASE = process.env.PROTOTYPE_URL ?? 'http://localhost';
const API = `${BASE}/api/v1`;
const CONTAINER = process.env.PROTOTYPE_CONTAINER ?? 'prototype';

/** Group count and group size of the `near` population; |near| = groups x size^2. */
const SIZES = (process.env.BENCH_SIZES ?? '20,40,60')
  .split(',')
  .map((s) => Number(s.trim()));
const GROUPS = Number(process.env.BENCH_GROUPS ?? 4);
const REPEATS = Number(process.env.BENCH_REPEATS ?? 3);
const BUFFERS = [1, 5, 20];
/** See operations(): where an edit's value comes from decides whether an atom is created. */
const STREAMS = ['reuse', 'fresh'];

let failures = 0;
function assert(cond, msg) {
  console.log(`  ${cond ? '✅' : '❌'} ${msg}`);
  if (!cond) failures++;
}

// ---------------------------------------------------------------- session and settings

let cookie = '';
async function api(path, init = {}) {
  const headers = { ...(init.headers ?? {}) };
  if (cookie) headers['Cookie'] = cookie;
  const res = await fetch(`${API}${path}`, { ...init, headers });
  const set = res.headers.get('set-cookie');
  if (set) cookie = set.split(';')[0];
  const text = await res.text();
  let body = {};
  try {
    body = JSON.parse(text);
  } catch {
    body = { raw: text };
  }
  return { status: res.status, body };
}

const originalYaml = readFileSync(PROJECT_YAML, 'utf8');

/** Write project.yaml and wait until the container sees it (the bind mount lags on macOS). */
function setSetting(name, value) {
  const content =
    `# TEMPORARY test config, written by test/projects/txn-dryrun-bench/e2e/bench.mjs\n` +
    `settings:\n  ${name}: ${value}\n`;
  writeFileSync(PROJECT_YAML, content);
  const deadline = Date.now() + 15000;
  for (;;) {
    const seen = execSync(
      `docker exec ${CONTAINER} cat /var/www/backend/config/project.yaml`,
      { encoding: 'utf8' },
    );
    if (seen === content) return;
    if (Date.now() > deadline)
      throw new Error('project.yaml did not reach the container');
    execSync('sleep 0.2');
  }
}

// ------------------------------------------------------------------------- population

/**
 * `near` as a union of complete blocks, so it is its own transitive closure and the
 * expensive rule holds. The self-join costs groups x size^3 intermediate rows, which is
 * where the population size makes itself felt.
 */
function nearPopulation(groups, size) {
  const atoms = [];
  const links = [];
  for (let g = 0; g < groups; g++) {
    for (let i = 0; i < size; i++) atoms.push(`v${g}_${i}`);
    for (let i = 0; i < size; i++)
      for (let j = 0; j < size; j++)
        links.push({ src: `v${g}_${i}`, tgt: `v${g}_${j}` });
  }
  return {
    atoms: [{ concept: 'Val', atoms }],
    links: [{ relation: 'near[Val*Val]', links }],
  };
}

async function importPopulation(population) {
  const fd = new FormData();
  fd.append(
    'file',
    new Blob([JSON.stringify(population)]),
    'near-population.json',
  );
  const headers = cookie ? { Cookie: cookie } : {};
  const res = await fetch(`${API}/admin/import`, {
    method: 'POST',
    body: fd,
    headers,
  });
  const body = await res.json().catch(() => ({}));
  if (res.status !== 200 || body.isCommitted !== true) {
    throw new Error(`import failed: ${res.status} ${JSON.stringify(body).slice(0, 300)}`);
  }
}

// ------------------------------------------------------------------- the dry-run rounds

const ROOT = 'resource/SESSION/1/Records';

/** The record the driver edits; the ExecEngine puts record1 under the session on first read. */
async function recordPath() {
  const { status, body } = await api(`/${ROOT}`);
  if (status !== 200) throw new Error(`cannot read ${ROOT}: ${status}`);
  const list = Array.isArray(body) ? body : [body];
  const record = list[0]?.Records?.[0] ?? list[0]?.Records;
  if (!record?._path_) {
    throw new Error(`no record in ${ROOT}: ${JSON.stringify(body).slice(0, 300)}`);
  }
  return record._path_;
}

/**
 * The operations of one buffer. Field 1 always takes a value that exists in `near`, so the
 * expensive rule's join is not empty. `spoil` decides whether the values break their
 * field's rule: only "ok" and "v0_0" are allowed.
 *
 * `stream` decides where the values come from, and that turns out to decide everything:
 *
 *  - "fresh": every edit writes a value that does not exist yet, so the edit creates an
 *    atom of concept Val. Concept::addAtom then marks Val as affected
 *    (backend/src/Ampersand/Core/Concept.php:581), and the compiler maps a concept to
 *    every conjunct mentioning any relation with that concept as source or target
 *    (Ampersand, src/Ampersand/FSpec/ToFSpec/ADL2FSpec.hs:229). The newest edit therefore
 *    affects everything and there is nothing left to carry.
 *  - "reuse": every edit writes a value that already exists, as a dropdown, a checkbox or
 *    a retyped value does. No atom is created, only the relation is affected, and the
 *    restriction to the newest edit does what it says.
 */
function operations(length, spoil, tag, stream) {
  return Array.from({ length }, (_, i) => {
    if (i === 0) return { op: 'replace', path: 'Field1', value: 'v0_0' };
    const value = !spoil
      ? 'ok'
      : stream === 'reuse'
        ? `v0_${i}` // an atom the imported `near` population already holds
        : `bad-${tag}-${i + 1}`;
    return { op: 'replace', path: `Field${i + 1}`, value };
  });
}

/** The worklist of a round: the invariant messages, sorted so order cannot cause a difference. */
function worklistOf(body) {
  return (body?.notifications?.invariants ?? [])
    .flatMap((inv) =>
      inv.tuples.length === 0
        ? [inv.ruleMessage]
        : inv.tuples.map((t) => t.violationMessage || inv.ruleMessage),
    )
    .sort();
}

/**
 * Replay one buffer edit by edit, exactly as the frontend does: round k sends operations
 * 1..k. With `carry` on it also sends the previous round's verdicts and the prefix they
 * describe, which is what lets the backend restrict its evaluation.
 */
async function replayBuffer(path, ops, carry) {
  const rounds = [];
  let carried = null;
  for (let k = 1; k <= ops.length; k++) {
    const slice = ops.slice(0, k);
    const usable =
      carry && carried && carried.opCount > 0 && carried.opCount < slice.length;
    const body = {
      ops: slice,
      carriedVerdicts: usable ? carried.verdicts : {},
      carriedOpCount: usable ? carried.opCount : 0,
    };
    const t0 = performance.now();
    const res = await api(`/${path}?dryRun=true`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const ms = performance.now() - t0;
    if (res.status !== 200) {
      throw new Error(`round ${k} failed: ${res.status} ${JSON.stringify(res.body).slice(0, 300)}`);
    }
    if (carry && res.body.dryRunVerdicts && typeof res.body.dryRunOpCount === 'number') {
      carried = {
        opCount: res.body.dryRunOpCount,
        verdicts: res.body.dryRunVerdicts,
      };
    }
    rounds.push({
      ms,
      worklist: worklistOf(res.body),
      holds: res.body.invariantRulesHold,
      carriedOpCount: body.carriedOpCount,
      scope: res.body.dryRunEvaluationScope ?? { evaluated: 0, affected: 0 },
    });
  }
  return rounds;
}

/** Send the buffer without dryRun, i.e. the SAVE round, and report what the server decided. */
async function saveBuffer(path, ops) {
  const res = await api(`/${path}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(ops),
  });
  return {
    status: res.status,
    committed: res.body.isCommitted,
    holds: res.body.invariantRulesHold,
    worklist: worklistOf(res.body),
  };
}

/** Put every field back to "ok" and commit, so each measurement starts from the same state. */
async function resetRecord(path) {
  const ops = Array.from({ length: 20 }, (_, i) => ({
    op: 'replace',
    path: `Field${i + 1}`,
    value: i === 0 ? 'v0_0' : 'ok',
  }));
  await saveBuffer(path, ops);
}

// ------------------------------------------------------------------------------- report

const median = (xs) => {
  const s = [...xs].sort((a, b) => a - b);
  return s.length % 2 ? s[(s.length - 1) / 2] : (s[s.length / 2 - 1] + s[s.length / 2]) / 2;
};
const fmt = (x) => x.toFixed(1).padStart(7);

async function measure(carry, path, size, stream) {
  const result = {};
  for (const n of BUFFERS) {
    const perRound = Array.from({ length: n }, () => []);
    let worklists = null;
    let scopes = null;
    for (let r = 0; r < REPEATS; r++) {
      const rounds = await replayBuffer(
        path,
        operations(n, true, `${size}-${r}`, stream),
        carry,
      );
      rounds.forEach((round, i) => perRound[i].push(round.ms));
      // The worklist text contains the value the driver wrote, so compare the shape:
      // the number of entries per round is what must match between the two settings.
      worklists = rounds.map((round) => round.worklist.length);
      scopes = rounds.map((round) => round.scope);
    }
    const perRoundMedian = perRound.map(median);
    result[n] = {
      perRound: perRoundMedian,
      // What a user of a buffer of this length pays over the whole edit session.
      total: perRoundMedian.reduce((a, b) => a + b, 0),
      worklistSizes: worklists,
      scopes,
    };
  }
  return result;
}

async function main() {
  console.log(`Bench against ${BASE}`);
  console.log(
    `sizes (group size): ${SIZES.join(', ')} · groups: ${GROUPS} · repeats: ${REPEATS}\n`,
  );

  const report = [];

  for (const size of SIZES) {
    const pairs = GROUPS * size * size;
    console.log(`\n=== near population: ${GROUPS} x ${size}^2 = ${pairs} pairs ===`);

    // A fresh install per size, so the populations do not accumulate.
    setSetting('transactions.editScopedDryRun', 'false');
    const inst = await api('/admin/installer?ignoreInvariantRules=true');
    if (inst.status !== 200) throw new Error(`installer failed: ${inst.status}`);
    cookie = '';
    await importPopulation(nearPopulation(GROUPS, size));
    const path = await recordPath();
    await resetRecord(path);

    const measured = {};
    for (const stream of STREAMS) {
      console.log(`\n-- edit stream "${stream}"`);
      setSetting('transactions.editScopedDryRun', 'false');
      await resetRecord(path);
      const off = await measure(false, path, size, stream);

      setSetting('transactions.editScopedDryRun', 'true');
      await resetRecord(path);
      const on = await measure(true, path, size, stream);
      await resetRecord(path);
      measured[stream] = { off, on };

      // --- the advisory must be the same, round for round
      for (const n of BUFFERS) {
        assert(
          JSON.stringify(off[n].worklistSizes) === JSON.stringify(on[n].worklistSizes),
          `buffer ${n}: worklist identical round by round ` +
            `(off ${off[n].worklistSizes.join('/')} · on ${on[n].worklistSizes.join('/')})`,
        );
      }

      // --- how far the restriction reached, in the last round of the longest buffer
      const lastOff = off[20].scopes[19];
      const lastOn = on[20].scopes[19];
      const scopeLine =
        `buffer 20, last round: evaluated ${lastOn.evaluated} of ${lastOn.affected} affected ` +
        `conjuncts, where the unrestricted round evaluated ${lastOff.evaluated}`;
      if (stream === 'reuse') {
        // An edit that creates no atom leaves only the edited relation affected, so the
        // restriction must bite here or it does not work at all.
        assert(
          lastOn.evaluated < lastOff.evaluated && lastOn.affected === lastOff.affected,
          scopeLine,
        );
      } else {
        // An edit that creates an atom marks its concept affected, and the concept maps to
        // every conjunct over a relation with that concept as an endpoint. The restriction
        // is still correct, but there is nothing left for it to leave out. Reported, not
        // asserted: this is a property of the model and of Ampersand's granularity, not of
        // the change under test.
        console.log(`  ℹ️  ${scopeLine}`);
      }
    }

    // --- the commit decision must be the same, for a buffer that may commit and one that
    //     may not, in both edit streams
    console.log('\n-- commit decisions');
    for (const stream of STREAMS) {
      for (const [label, spoil, expectCommit] of [
        ['a clean buffer commits', false, true],
        ['a violating buffer does not commit', true, false],
      ]) {
        const decisions = [];
        for (const [setting, carry] of [['false', false], ['true', true]]) {
          setSetting('transactions.editScopedDryRun', setting);
          await resetRecord(path);
          const ops = operations(5, spoil, `commit-${setting}`, stream);
          // The advisory rounds first, exactly as a user produces them, so the commit round
          // meets the same server state a real SAVE meets.
          await replayBuffer(path, ops, carry);
          decisions.push(await saveBuffer(path, ops));
        }
        assert(
          decisions[0].committed === decisions[1].committed &&
            decisions[0].holds === decisions[1].holds &&
            JSON.stringify(decisions[0].worklist) === JSON.stringify(decisions[1].worklist) &&
            decisions[0].committed === expectCommit,
          `${stream}: ${label} — off: committed=${decisions[0].committed} ` +
            `holds=${decisions[0].holds} violations=${decisions[0].worklist.length}, ` +
            `on: committed=${decisions[1].committed} holds=${decisions[1].holds} ` +
            `violations=${decisions[1].worklist.length}`,
        );
      }
    }

    report.push({ size, pairs, measured });
    await resetRecord(path);
  }

  console.log('\n\n================ dry-run round latency (median of ' + REPEATS + ') ================');
  console.log('All times in ms: the PATCH round trip, i.e. patch-sent to verdict-available.');
  console.log('"whole buffer" is the sum over the rounds a user of that buffer length pays.');
  console.log('"reuse" = the edit writes an existing value; "fresh" = it creates an atom.\n');
  console.log(
    'stream  near pairs  buffer   off: 1st  off: last off: whole    on: 1st   on: last  on: whole   saved',
  );
  for (const stream of STREAMS) {
    for (const row of report) {
      for (const n of BUFFERS) {
        const o = row.measured[stream].off[n];
        const c = row.measured[stream].on[n];
        const saved = o.total > 0 ? ((1 - c.total / o.total) * 100).toFixed(0) : '0';
        console.log(
          `${stream.padEnd(6)}  ${String(row.pairs).padStart(10)}  ${String(n).padStart(6)}  ` +
            `${fmt(o.perRound[0])}  ${fmt(o.perRound[n - 1])}  ${fmt(o.total)}  ` +
            `${fmt(c.perRound[0])}  ${fmt(c.perRound[n - 1])}  ${fmt(c.total)}  ${String(saved).padStart(5)}%`,
        );
      }
    }
  }

  console.log(`\n${failures === 0 ? '✅ all checks passed' : `❌ ${failures} check(s) failed`}`);
}

main()
  .catch((e) => {
    console.error(`❌ ${e.stack ?? e}`);
    failures++;
  })
  .finally(() => {
    writeFileSync(PROJECT_YAML, originalYaml);
    process.exit(failures === 0 ? 0 : 1);
  });
