// End-to-end regression for the runtime population importer (issue #1673).
//
// Guards the core promise of dual-format import: a JSON upload and an equivalent YAML
// upload must produce EXACTLY the same population, and the file extension must be
// advisory (content decides the format). The runner installs the model before this
// spec runs; each step below re-installs to start from a known-empty database.
//
// Run by test/run-regression.sh (picked up as e2e/*.mjs). Env in:
//   PROTOTYPE_URL       base URL of the running prototype (e.g. http://localhost:9400)
//   PROTOTYPE_CONTAINER prototype (web) container name (e.g. reg-import-prototype)
// Exit 0 = all assertions pass, 1 = failure.

import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = dirname(fileURLToPath(import.meta.url));
const BASE = process.env.PROTOTYPE_URL || 'http://localhost:9400';
const PROTO = process.env.PROTOTYPE_CONTAINER || 'reg-import-prototype';
const DB_CONTAINER = PROTO.replace(/-prototype$/, '-db');

// Relations whose contents we compare (labels as used in model/main.adl).
const RELATIONS = ['name', 'worksFor', 'orgName'];

let failed = false;
const pass = (m) => console.log(`  PASS  ${m}`);
const fail = (m) => { console.log(`  FAIL  ${m}`); failed = true; };

function docker(container, ...cmd) {
  return execFileSync('docker', ['exec', container, ...cmd], { encoding: 'utf8' });
}

function mysql(sql) {
  return docker(DB_CONTAINER, 'mysql', '-uampersand', '-pampersand', '-N', '-e', sql, DB_NAME).trim();
}

// The single application database (everything that is not a MariaDB system schema).
function findDbName() {
  const out = docker(
    DB_CONTAINER, 'mysql', '-uampersand', '-pampersand', '-N', '-e',
    "SELECT schema_name FROM information_schema.schemata " +
    "WHERE schema_name NOT IN ('information_schema','mysql','performance_schema','sys');",
  ).trim();
  const names = out.split('\n').map((s) => s.trim()).filter(Boolean);
  if (names.length !== 1) {
    throw new Error(`Expected exactly one application database, found: ${names.join(', ') || '(none)'}`);
  }
  return names[0];
}

// Model-driven table/column mapping straight from the compiler output, so the dump does
// not hard-code generated table names.
function relationMap() {
  const rels = JSON.parse(docker(PROTO, 'cat', '/var/www/backend/generics/relations.json'));
  const map = {};
  for (const r of rels) {
    if (RELATIONS.includes(r.label)) {
      map[r.label] = {
        table: r.mysqlTable.name,
        src: r.mysqlTable.srcCol.name,
        tgt: r.mysqlTable.tgtCol.name,
      };
    }
  }
  for (const label of RELATIONS) {
    if (!map[label]) throw new Error(`Relation '${label}' not found in relations.json`);
  }
  return map;
}

// Canonical, order-independent dump of the guarded relations: the population's fingerprint.
function dumpPopulation(relMap) {
  const parts = [];
  for (const label of RELATIONS) {
    const { table, src, tgt } = relMap[label];
    const rows = mysql(
      `SELECT CONCAT(\`${src}\`, '|', \`${tgt}\`) FROM \`${table}\` ` +
      `WHERE \`${src}\` IS NOT NULL AND \`${tgt}\` IS NOT NULL ORDER BY 1;`,
    );
    parts.push(`## ${label}\n${rows}`);
  }
  return parts.join('\n');
}

async function reinstall() {
  const res = await fetch(`${BASE}/api/v1/admin/installer?ignoreInvariantRules=true`);
  if (!res.ok) throw new Error(`Installer failed: HTTP ${res.status}`);
}

async function importFile(bytes, filename) {
  const fd = new FormData();
  fd.append('file', new Blob([bytes]), filename);
  const res = await fetch(`${BASE}/api/v1/admin/import`, { method: 'POST', body: fd });
  let body = {};
  try { body = await res.json(); } catch { /* non-JSON error body */ }
  return { status: res.status, committed: body.isCommitted === true, body };
}

const DB_NAME = findDbName();

const jsonBytes = readFileSync(join(DIR, 'population.json'));
const yamlBytes = readFileSync(join(DIR, 'population.yaml'));

async function main() {
  console.log(`== importer equivalence (db=${DB_NAME}) ==`);
  const relMap = relationMap();

  // 1. JSON import into the freshly installed (empty) database.
  await reinstall();
  const j = await importFile(jsonBytes, 'population.json');
  j.committed ? pass('JSON import committed') : fail(`JSON import not committed (HTTP ${j.status})`);
  const dumpJson = dumpPopulation(relMap);

  // 2. Same population as YAML into an empty database — must yield the identical result.
  await reinstall();
  const y = await importFile(yamlBytes, 'population.yaml');
  y.committed ? pass('YAML import committed') : fail(`YAML import not committed (HTTP ${y.status})`);
  const dumpYaml = dumpPopulation(relMap);

  if (dumpJson === dumpYaml && dumpJson !== '') {
    pass('JSON and YAML produce an identical population');
  } else {
    fail('JSON and YAML populations differ');
    console.log('--- JSON dump ---\n' + dumpJson);
    console.log('--- YAML dump ---\n' + dumpYaml);
  }

  // 3. Extension is advisory: the JSON content under a .txt name auto-detects and imports.
  await reinstall();
  const t = await importFile(jsonBytes, 'population.txt');
  t.committed ? pass('JSON content as .txt is auto-detected and committed')
              : fail(`.txt auto-detection failed (HTTP ${t.status})`);
  if (dumpPopulation(relMap) === dumpJson) {
    pass('.txt import yields the same population as .json');
  } else {
    fail('.txt import differs from .json import');
  }

  // 4. YAML content without any extension also auto-detects.
  await reinstall();
  const n = await importFile(yamlBytes, 'population');
  n.committed ? pass('YAML content without extension is auto-detected and committed')
              : fail(`extensionless auto-detection failed (HTTP ${n.status})`);

  console.log(failed ? '\n==== IMPORT EQUIVALENCE FAILED ====' : '\n==== IMPORT EQUIVALENCE PASSED ====');
  process.exit(failed ? 1 : 0);
}

main().catch((e) => { console.error(e); process.exit(1); });
