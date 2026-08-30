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
 *      appears in the debug log during the on-run and never during the off-run.
 *
 * Run via `test/run-regression.sh skip-clean-conjuncts` (the runner prepares
 * the backend API), or against the dev stack with this project compiled:
 * node test/projects/skip-clean-conjuncts/e2e/parity.mjs
 */
import { readFileSync, rmSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import {
  activateAnonymous, bookingScenario, countIn, debugLoggingPhp, makeClient,
  reporter, runInstaller, settingsYaml, sleep, writeConfig,
} from '../../../shared/conjunct-parity.mjs';

const SPEC = 'test/projects/skip-clean-conjuncts/e2e/parity.mjs';
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

const SKIP_LINE = 'Skip evaluation of conjunct';
const skipCount = () => countIn(debugLog, SKIP_LINE);

// Create a fresh session (the role activation request carries the
// session-creation transaction) and return how many skip lines it produced.
async function sessionProbe() {
  const before = skipCount();
  await activateAnonymous(client);
  return skipCount() - before;
}

// Wait until the backend's behavior matches the flipped setting: a fresh
// session's transaction close skips conjuncts exactly when the setting is on.
// This covers both the project.yaml and the temporary logging.php becoming
// effective (the macOS bind mount can serve Apache a stale file for a short
// while after the host wrote it), since the skip is only observable through
// the log.
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
    await sleep(500);
  }
}

try {
  console.log('▶ Enabling DEBUG logging to a bind-mounted file (temporary logging.php)');
  rmSync(debugLog, { force: true });
  writeConfig(loggingPhp, debugLoggingPhp(`/var/www/${SPEC.replace(/parity\.mjs$/, '.debug.log')}`, SPEC));

  console.log('\n▶ Phase 1: transactions.skipCleanConjuncts off (default)');
  writeConfig(projectYaml, settingsYaml({}, SPEC));
  await runInstaller(baseUrl);
  await waitForSettingEffective(false);
  const skipsBeforeOff = skipCount();
  const digestOff = await bookingScenario(client, 'off', assert);
  const skipsDuringOff = skipCount() - skipsBeforeOff;
  assert(skipsDuringOff === 0, `no conjunct evaluation skipped with the setting off (saw ${skipsDuringOff})`);

  console.log('\n▶ Phase 2: transactions.skipCleanConjuncts on');
  writeConfig(projectYaml, settingsYaml({ 'transactions.skipCleanConjuncts': true }, SPEC));
  await runInstaller(baseUrl);
  await waitForSettingEffective(true);
  const skipsBeforeOn = skipCount();
  const digestOn = await bookingScenario(client, 'on', assert);
  const skipsDuringOn = skipCount() - skipsBeforeOn;
  assert(skipsDuringOn > 0, `the skip fires with the setting on (saw ${skipsDuringOn} skipped evaluations)`);

  console.log('\n▶ Parity: off-digest versus on-digest');
  assert(digestOff === digestOn, 'digests are identical with the setting off and on');
  if (digestOff !== digestOn) {
    console.error('--- digest off ---\n' + digestOff);
    console.error('--- digest on ----\n' + digestOn);
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
