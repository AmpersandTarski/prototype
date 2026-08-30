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
 *      protocol and no shadow mismatch was logged.
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
  settingsYaml, waitForCanary, waitForDebugLog, writeConfig,
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

  for (const mode of ['off', 'shadow', 'on']) {
    console.log(`\n▶ transactions.deltaConjunctMaintenance: '${mode}'`);
    // The menuMode value is a canary: it proves the backend reads this file
    writeConfig(projectYaml, settingsYaml({
      'transactions.deltaConjunctMaintenance': `'${mode}'`,
      'frontend.menuMode': `canary-${mode}`,
    }, SPEC));
    await runInstaller(baseUrl);
    await waitForCanary(client, `canary-${mode}`);

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
    digests[mode] = await bookingScenario(client, mode, assert);

    const deltaMaintained = countIn(debugLog, DELTA_MAINTAINED) - before.delta;
    const mismatches = countIn(debugLog, MISMATCH) - before.mismatch;
    assert(deltaMaintained === 0, `[${mode}] no conjunct went through the delta protocol (saw ${deltaMaintained})`);
    assert(mismatches === 0, `[${mode}] no shadow mismatch logged (saw ${mismatches})`);
    if (mode === 'off') {
      const ran = summariesNow() - before.summaries;
      assert(ran === 0, `[off] the delta path did not run (saw ${ran} summary lines)`);
    } else {
      const lines = summaryLines(mode).slice(before.summaries);
      assert(lines.length > 0, `[${mode}] the delta path ran (saw ${lines.length} close summaries)`);
      assert(lines.every((l) => l.includes(' 0 delta-maintained,')),
        `[${mode}] every close reports 0 delta-maintained (compiler without deltaQueries)`);
    }
  }

  console.log('\n▶ Parity across modes');
  assert(digests.off === digests.shadow, 'digests are identical for off and shadow');
  assert(digests.off === digests.on, 'digests are identical for off and on');
  for (const mode of ['shadow', 'on']) {
    if (digests.off !== digests[mode]) {
      console.error(`--- digest off ---\n${digests.off}\n--- digest ${mode} ---\n${digests[mode]}`);
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
