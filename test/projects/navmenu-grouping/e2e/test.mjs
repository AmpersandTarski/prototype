/**
 * Regression test for issue #406: navigation menu grouping by interface
 * expression type + idempotent (re)install of the nav menu population.
 *
 * Prerequisites: dev stack running (`docker compose up -d` in the repo root).
 * Run: node test/projects/navmenu-grouping/e2e/test.mjs
 *
 * The script:
 * 1. compiles test/projects/navmenu-grouping into the backend (no frontend build);
 * 2. with frontend.menuGrouping=none: asserts a flat menu;
 * 3. with frontend.menuGrouping=byType: asserts task screens top-level and
 *    list interfaces under one group item, and asserts the configurable label;
 * 4. runs the installer twice and asserts the nav menu population is identical
 *    (idempotence, no stale/duplicate items);
 * 5. asserts the frontend.menuMode setting is exposed in the navbar response.
 */
import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..');
const projectYaml = resolve(repoRoot, 'backend/config/project.yaml');
const baseUrl = process.env.PROTOTYPE_URL ?? 'http://localhost';

const originalYaml = readFileSync(projectYaml, 'utf8');

let failures = 0;
function assert(cond, msg) {
  if (cond) {
    console.log(`  ✅ ${msg}`);
  } else {
    console.error(`  ❌ ${msg}`);
    failures++;
  }
}

function setSettings(settings) {
  const lines = Object.entries(settings)
    .map(([k, v]) => `  ${k}: ${v}`)
    .join('\n');
  const content = `# TEMPORARY test config, written by test/projects/navmenu-grouping/e2e/test.mjs\nsettings:\n${lines}\n`;
  writeFileSync(projectYaml, content);

  // The macOS bind mount propagates file changes with a delay; wait until the
  // container sees the new settings before hitting the installer
  const deadline = Date.now() + 15000;
  for (;;) {
    const inContainer = execSync(
      'docker exec prototype cat /var/www/backend/config/project.yaml',
      { encoding: 'utf8' },
    );
    if (inContainer === content) {
      return;
    }
    if (Date.now() > deadline) {
      throw new Error('project.yaml did not propagate to the container');
    }
    execSync('sleep 0.2');
  }
}

async function runInstaller() {
  const resp = await fetch(`${baseUrl}/api/v1/admin/installer`);
  if (!resp.ok) {
    throw new Error(`Installer failed: ${resp.status} ${await resp.text()}`);
  }
}

async function getNavbar() {
  const resp = await fetch(`${baseUrl}/api/v1/app/navbar`);
  if (!resp.ok) {
    throw new Error(`navbar failed: ${resp.status} ${await resp.text()}`);
  }
  return resp.json();
}

// Menu items of the model's interfaces + group items, excluding the
// PrototypeContext admin interfaces and the menu root itself
function modelNavs(navbar) {
  return navbar.navs.filter(
    (n) => !n.ifc?.startsWith('PrototypeContext.') && n.parent != null,
  );
}

function byIfc(navs, ifc) {
  return navs.find((n) => n.ifc === ifc);
}

const TASKS = ['SearchRequirements', 'UpcomingRequirements'];
const LISTS = [
  'Countries',
  'Organisms',
  'ProductForms',
  'Requirements',
  'Suppliers',
];

try {
  console.log('▶ Compiling navmenu-grouping model into backend ...');
  execSync(
    'docker exec prototype sh -c "ampersand proto --no-frontend ' +
      '/var/www/test/projects/navmenu-grouping/model/main.adl ' +
      '--proto-dir /var/www/backend --crud-defaults cRud"',
    { stdio: 'inherit' },
  );

  console.log('\n▶ Case 1: frontend.menuGrouping=none (default) → flat menu');
  setSettings({});
  await runInstaller();
  let navbar = await getNavbar();
  let navs = modelNavs(navbar);
  const root = navbar.navs.find((n) => n.parent == null && n.ifc == null);
  assert(root != null, `menu root exists (id: ${root?.id})`);
  for (const ifc of [...TASKS, ...LISTS]) {
    const nav = byIfc(navs, ifc);
    assert(
      nav != null && nav.parent === root.id,
      `${ifc} is a top-level item`,
    );
  }
  assert(
    !navs.some((n) => n.ifc == null),
    'no group item present',
  );
  assert(navbar.menuMode === 'static', 'menuMode defaults to static');

  console.log('\n▶ Case 2: frontend.menuGrouping=byType → grouped menu');
  setSettings({
    'frontend.menuGrouping': 'byType',
    'frontend.menuMode': 'horizontal',
  });
  await runInstaller();
  navbar = await getNavbar();
  navs = modelNavs(navbar);
  const group = navs.find((n) => n.ifc == null);
  assert(group != null, `group item exists (id: ${group?.id})`);
  assert(group?.label === 'Lists', `group label is 'Lists' (got: ${group?.label})`);
  assert(
    group != null && group.parent === root.id,
    'group item sits under the menu root',
  );
  for (const ifc of TASKS) {
    const nav = byIfc(navs, ifc);
    assert(
      nav != null && nav.parent === root.id,
      `${ifc} (task screen) stays top-level`,
    );
  }
  for (const ifc of LISTS) {
    const nav = byIfc(navs, ifc);
    assert(
      nav != null && group != null && nav.parent === group.id,
      `${ifc} (list) is grouped under '${group?.label}'`,
    );
  }
  assert(
    navbar.menuMode === 'horizontal',
    'frontend.menuMode is exposed in the navbar response',
  );

  console.log('\n▶ Case 3: custom group label');
  setSettings({
    'frontend.menuGrouping': 'byType',
    'frontend.menuGroupingLabel': 'Reference lists',
  });
  await runInstaller();
  navbar = await getNavbar();
  const customGroup = modelNavs(navbar).find((n) => n.ifc == null);
  assert(
    customGroup?.label === 'Reference lists',
    `group label follows frontend.menuGroupingLabel (got: ${customGroup?.label})`,
  );

  console.log('\n▶ Case 4: reinstalling is idempotent');
  const key = (n) => `${n.ifc}|${n.label}|${n.parent}|${n.seqNr}`;
  const before = navbar.navs.map(key).sort();
  await runInstaller();
  navbar = await getNavbar();
  const after = navbar.navs.map(key).sort();
  assert(
    before.length === after.length &&
      before.every((k, i) => k === after[i]),
    `nav menu population identical after reinstall (${before.length} items, no stale/duplicate items)`,
  );
} finally {
  writeFileSync(projectYaml, originalYaml);
  console.log('\n(project.yaml restored)');
}

if (failures > 0) {
  console.error(`\n❌ ${failures} assertion(s) failed`);
  process.exit(1);
}
console.log('\n✅ All assertions passed');
