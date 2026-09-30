/**
 * Shared set-up of the browser specs in test/projects/<project>/e2e/: where the working copy
 * and the stack are, counting assertions, running commands, loading Puppeteer, and building
 * the Angular frontend of one project.
 *
 * The runner (test/run-regression.sh) sets PROTOTYPE_URL and PROTOTYPE_CONTAINER to its own
 * stack; without them a spec runs against the dev stack.
 */
import { execSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const testDir = resolve(dirname(fileURLToPath(import.meta.url)), '..');
export const repoRoot = resolve(testDir, '..');
export const baseUrl = process.env.PROTOTYPE_URL ?? 'http://localhost';
export const container = process.env.PROTOTYPE_CONTAINER ?? 'prototype';

let failures = 0;

export function assert(cond, msg) {
  if (cond) {
    console.log(`  ✅ ${msg}`);
  } else {
    console.error(`  ❌ ${msg}`);
    failures++;
  }
}

export function failureCount() {
  return failures;
}

export function run(cmd, opts = {}) {
  execSync(cmd, { stdio: 'inherit', cwd: repoRoot, ...opts });
}

// A fresh worktree has no test/node_modules yet (gitignored); install them on demand.
export async function loadPuppeteer() {
  const require = createRequire(import.meta.url);
  try {
    require.resolve('puppeteer');
  } catch {
    run('npm install --no-audit --no-fund', { cwd: testDir });
  }
  return (await import('puppeteer')).default;
}

/* Build the Angular frontend of a project into html/ (the regression runner only copies the
 * backend API there). Same recipe as generate.sh: compiler-generated sources, npm build,
 * copy the dist over html/. */
export function buildFrontend(project) {
  console.log('▶ Building the frontend (compiler sources + npm build) ...');
  run(
    `docker exec ${container} sh -c "ampersand proto --frontend-version Angular --no-backend ` +
      `/var/www/test/projects/${project}/model/main.adl ` +
      `--proto-dir /var/www/frontend/src/app/generated --crud-defaults cRud"`,
  );
  run('npm install --no-audit --no-fund', { cwd: resolve(repoRoot, 'frontend') });
  run('npm run build:dev', { cwd: resolve(repoRoot, 'frontend') });
  run('cp -r frontend/dist/prototype-frontend/. html/');
}
