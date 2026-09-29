/**
 * Regression test for transactions.deltaConjunctMaintenance (Ampersand#1684).
 *
 * The setting selects how the transaction close maintains the violation cache:
 * `off` (full re-evaluation), `shadow` (delta protocol plus full evaluation,
 * full result authoritative, differences logged) or `on` (delta protocol for
 * the supported class). The delta protocol needs candidate queries that only a
 * compiler with delta-sql emits; with the compiler bundled in this repository
 * every conjunct lacks them, so all three modes must behave identically and the
 * non-off modes must route every conjunct to full evaluation.
 *
 * This spec runs one API-level scenario under each mode and requires:
 *   1. byte-identical digests across off, shadow and on;
 *   2. inside each run: rollback on a violating edit, commit on valid edits, the
 *      ExecEngine repair, and signals that follow the data;
 *   3. the non-off modes really ran: the close's summary debug line names the
 *      mode and reports "0 delta-maintained"; no conjunct went through the delta
 *      protocol and no shadow mismatch was logged;
 *   4. mode 'on' together with transactions.skipCleanConjuncts gives the same
 *      digest, and the summary line counts conjuncts skipped as clean;
 *   5. a value outside 'off', 'shadow' and 'on' (a YAML false here) stops the
 *      application at boot: every request answers HTTP 500.
 *
 * When a compiler that emits deltaQueries is bundled, extend this spec so the
 * summary line reports delta-maintained conjuncts and the shadow run logs
 * "identical" checks — that is the guard of the delta path itself.
 *
 * Run via `test/run-regression.sh delta-conjunct-maintenance`.
 */
import { existsSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import {
  bookingScenario, countIn, debugLoggingPhp, makeClient, reporter, runInstaller,
  settingsYaml, waitForCanary, waitForDebugLog, waitForLogAfterGet, writeConfig,
} from '../../../shared/conjunct-parity.mjs';

const SPEC = 'test/projects/delta-conjunct-maintenance/e2e/parity.mjs';
const e2eDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(e2eDir, '../../../..');
const projectYaml = resolve(repoRoot, 'backend/config/project.yaml');
const loggingPhp = resolve(repoRoot, 'backend/config/logging.php');
const debugLog = resolve(e2eDir, '.debug.log');
const baseUrl = process.env.PROTOTYPE_URL ?? 'http://localhost';

const originalYaml = readFileSync(projectYaml, 'utf8');
const originalLogging = readFileSync(loggingPhp, 'utf8');
const report = reporter();
const { assert } = report;
const client = makeClient(baseUrl);

const SUMMARY = (mode) => `Delta conjunct maintenance ('${mode}'):`;
const DELTA_MAINTAINED = 'cache maintained by delta protocol for relations';
const MISMATCH = 'DELTA SHADOW MISMATCH';
const BOOT_CHECK = "'transactions.deltaConjunctMaintenance' must be 'off', 'shadow' or 'on'";

// Lines of the summary form "Delta conjunct maintenance ('<mode>'): N delta-maintained, ..."
function summaryLines(mode) {
  if (!existsSync(debugLog)) {
    return [];
  }
  return readFileSync(debugLog, 'utf8')
    .split('\n')
    .filter((line) => line.includes(SUMMARY(mode)));
}

const digests = {};
try {
  console.log('▶ Enabling DEBUG logging to a bind-mounted file (temporary logging.php)');
  rmSync(debugLog, { force: true });
  writeConfig(loggingPhp, debugLoggingPhp(`/var/www/${SPEC.replace(/parity\.mjs$/, '.debug.log')}`, SPEC));
  await waitForDebugLog(client, debugLog);

  // 'on+skip' is mode 'on' with transactions.skipCleanConjuncts: both evaluation
  // shortcuts share Transaction::isSkippableCleanConjunct(), so they are tested together
  for (const phase of ['off', 'shadow', 'on', 'on+skip']) {
    const mode = phase === 'on+skip' ? 'on' : phase;
    console.log(`\n▶ transactions.deltaConjunctMaintenance: '${mode}'${phase === 'on+skip' ? ' with skipCleanConjuncts' : ''}`);
    // The menuMode value is a canary: it proves the backend reads this file
    writeConfig(projectYaml, settingsYaml({
      'transactions.deltaConjunctMaintenance': `'${mode}'`,
      ...(phase === 'on+skip' ? { 'transactions.skipCleanConjuncts': true } : {}),
      'frontend.menuMode': `canary-${phase.replace('+', '-')}`,
    }, SPEC));
    // Install only once the backend reads the new file completely: the macOS bind
    // mount can serve Apache a stale or half-written copy for a moment
    await waitForCanary(client, `canary-${phase.replace('+', '-')}`);
    await runInstaller(baseUrl);

    // Baselines: the log also holds lines from before this phase (the runner
    // installs with whatever project.yaml the working copy had)
    const summariesNow = () => (mode === 'off'
      ? summaryLines('shadow').length + summaryLines('on').length
      : summaryLines(mode).length);
    const before = {
      delta: countIn(debugLog, DELTA_MAINTAINED),
      mismatch: countIn(debugLog, MISMATCH),
      summaries: summariesNow(),
    };
    digests[phase] = await bookingScenario(client, phase, assert);

    const deltaMaintained = countIn(debugLog, DELTA_MAINTAINED) - before.delta;
    const mismatches = countIn(debugLog, MISMATCH) - before.mismatch;
    assert(deltaMaintained === 0, `[${phase}] no conjunct went through the delta protocol (saw ${deltaMaintained})`);
    assert(mismatches === 0, `[${phase}] no shadow mismatch logged (saw ${mismatches})`);
    if (mode === 'off') {
      const ran = summariesNow() - before.summaries;
      assert(ran === 0, `[off] the delta path did not run (saw ${ran} summary lines)`);
    } else {
      const lines = summaryLines(mode).slice(before.summaries);
      assert(lines.length > 0, `[${phase}] the delta path ran (saw ${lines.length} close summaries)`);
      assert(lines.every((l) => l.includes(' 0 delta-maintained,')),
        `[${phase}] every close reports 0 delta-maintained (compiler without deltaQueries)`);
      const skipped = lines.reduce((n, l) => n + Number(/(\d+) skipped as clean/.exec(l)?.[1] ?? 0), 0);
      if (phase === 'on+skip') {
        assert(skipped > 0, `[on+skip] the delta path skips clean conjuncts (saw ${skipped})`);
      } else {
        assert(skipped === 0, `[${phase}] no conjunct skipped as clean without skipCleanConjuncts (saw ${skipped})`);
      }
    }
  }

  console.log("\n▶ transactions.deltaConjunctMaintenance: false (outside 'off', 'shadow' and 'on')");
  writeConfig(projectYaml, settingsYaml({ 'transactions.deltaConjunctMaintenance': false }, SPEC));
  // Any HTTP 500 is not proof: a half-propagated project.yaml gives one too. The
  // uncaught boot exception is logged with its own message, so wait for that.
  const rejected = await waitForLogAfterGet(baseUrl, 'api/v1/app/navbar', debugLog, BOOT_CHECK);
  assert(rejected.status === 500 && rejected.seen,
    `a value outside the three stops the application at boot (navbar answered ${rejected.status}, message logged: ${rejected.seen})`);

  console.log('\n▶ Parity across modes');
  assert(digests.off === digests.shadow, 'digests are identical for off and shadow');
  assert(digests.off === digests.on, 'digests are identical for off and on');
  assert(digests.off === digests['on+skip'], 'digests are identical for off and on with skipCleanConjuncts');
  for (const phase of ['shadow', 'on', 'on+skip']) {
    if (digests.off !== digests[phase]) {
      console.error(`--- digest off ---\n${digests.off}\n--- digest ${phase} ---\n${digests[phase]}`);
    }
  }
} catch (e) {
  report.fail(e.message);
} finally {
  // Leave the working copy as found
  writeFileSync(projectYaml, originalYaml);
  writeFileSync(loggingPhp, originalLogging);
  rmSync(debugLog, { force: true });
}

process.exit(report.failures === 0 ? 0 : 1);
