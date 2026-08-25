/**
 * Regression test for the sortByAndHide BOX<TABLE> annotation: rows sort on a
 * column that is not rendered.
 *
 * Prerequisites: dev stack running (`docker compose up -d` in the repo root),
 * or run under test/run-regression.sh, which provides its own stack.
 * Run: node test/projects/box-annotations/e2e/test.mjs
 *
 * The script:
 * 1. compiles test/projects/box-annotations into the backend (no frontend build);
 * 2. asserts over the API that `sortByAndHide` alone makes the backend deliver
 *    `_sortValues_` (no `sortable` annotation on the box), that `sortable` still
 *    does, and that a box without either stays free of `_sortValues_`;
 * 3. generates the Angular frontend sources into a scratch dir inside the
 *    container, using the working copy's .templates, and asserts the rendered
 *    component HTML: the table sorts on the hidden column and every cell of
 *    that column carries the constant-false *ngIf that drops it.
 *
 * What this does not cover: an Angular production build of the generated
 * sources. Verify that once by hand with `./generate.sh box-annotations`.
 */
import { execSync } from 'node:child_process';

const baseUrl = process.env.PROTOTYPE_URL ?? 'http://localhost';
// test/run-regression.sh runs this spec against its own stack; without it, the dev stack.
const container = process.env.PROTOTYPE_CONTAINER ?? 'prototype';

const MODEL = '/var/www/test/projects/box-annotations/model/main.adl';
const SCRATCH = '/tmp/box-annotations-frontend-gen';

let failures = 0;
function assert(cond, msg) {
  if (cond) {
    console.log(`  ✅ ${msg}`);
  } else {
    console.error(`  ❌ ${msg}`);
    failures++;
  }
}

function inContainer(cmd) {
  return execSync(`docker exec ${container} sh -c ${JSON.stringify(cmd)}`, {
    encoding: 'utf8',
  });
}

async function getJson(path) {
  const resp = await fetch(`${baseUrl}${path}`);
  if (!resp.ok) {
    throw new Error(`GET ${path} failed: ${resp.status} ${await resp.text()}`);
  }
  return resp.json();
}

console.log('▶ Compiling box-annotations model into backend ...');
execSync(
  `docker exec ${container} sh -c "ampersand proto --no-frontend ${MODEL} ` +
    '--proto-dir /var/www/backend --crud-defaults cRud"',
  { stdio: 'inherit' },
);

console.log('\n▶ Installing application ...');
const resp = await fetch(`${baseUrl}/api/v1/admin/installer?ignoreInvariantRules=true`);
if (!resp.ok) {
  throw new Error(`Installer failed: ${resp.status} ${await resp.text()}`);
}

console.log('\n▶ Case 1: sortByAndHide (without sortable) delivers _sortValues_');
const hidden = await getJson('/api/v1/resource/SESSION/1/CategoriesSortedHidden');
assert(Array.isArray(hidden) && hidden.length === 3, `3 rows (got: ${hidden.length})`);
const byName = Object.fromEntries(hidden.map((r) => [r.Name, r]));
for (const [name, rank] of [['Beta', '1'], ['Gamma', '2'], ['Alpha', '3']]) {
  assert(
    byName[name]?._sortValues_?.Rank === rank,
    `row '${name}' carries _sortValues_.Rank = '${rank}' (got: ${byName[name]?._sortValues_?.Rank})`,
  );
}

console.log('\n▶ Case 2: sortable still delivers _sortValues_; a plain box does not');
const sortable = await getJson('/api/v1/resource/SESSION/1/CategoriesSortable');
assert(
  sortable.every((r) => r._sortValues_?.Name != null),
  'CategoriesSortable (sortable) rows carry _sortValues_',
);
const plain = await getJson('/api/v1/resource/SESSION/1/Categories');
assert(
  plain.every((r) => r._sortValues_ === undefined),
  'Categories (no sort annotation) rows carry no _sortValues_',
);

console.log('\n▶ Case 3: generated component HTML hides the sort column');
try {
  inContainer(
    `rm -rf ${SCRATCH} && mkdir -p ${SCRATCH}` +
      ` && cp -r /var/www/frontend/src/app/generated/.templates ${SCRATCH}/` +
      ` && ampersand proto --frontend-version Angular --no-backend ${MODEL}` +
      ` --proto-dir ${SCRATCH} --crud-defaults cRud`,
  );
  const hiddenHtml = inContainer(
    `cat ${SCRATCH}/*categoriessortedhidden*/*.component.html`,
  );
  assert(
    hiddenHtml.includes('sortBy="_sortValues_.Rank"'),
    'table sorts on _sortValues_.Rank',
  );
  const dropRank = (hiddenHtml.match(/\*ngIf="'Rank' !== 'Rank'"/g) ?? []).length;
  assert(
    dropRank >= 2,
    `header and row cells of 'Rank' carry the dropping *ngIf (found ${dropRank})`,
  );
  assert(
    hiddenHtml.includes(`*ngIf="'Name' !== 'Rank'"`),
    `the visible 'Name' column keeps rendering (constant-true *ngIf)`,
  );

  const sortableHtml = inContainer(
    `cat ${SCRATCH}/*categoriessortable*/*.component.html`,
  );
  assert(
    !sortableHtml.includes('sortBy=') && !sortableHtml.includes('*ngIf="\''),
    'a table without sortByAndHide renders as before (no sortBy binding, no column *ngIf)',
  );
} finally {
  inContainer(`rm -rf ${SCRATCH}`);
}

if (failures > 0) {
  console.error(`\n❌ ${failures} assertion(s) failed`);
  process.exit(1);
}
console.log('\n✅ All assertions passed');
