// End-to-end proof that the runtime (backend) importer reads a real FC5 population as YAML.
//
// The runner compiles model/main.adl (relations only, no population) and installs an EMPTY
// database. This spec uploads e2e/GibraltarBB.yml — the Gibraltar_Bloembollen_exporteisen
// data in the atoms/links YAML format — and asserts the resulting population, so we prove the
// upload imports flawlessly. The compile-time counterpart (the Ampersand compiler reading the
// same data via INCLUDE) is checked separately; see README.
//
// Env in: PROTOTYPE_URL, PROTOTYPE_CONTAINER. Exit 0 = pass.

import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = dirname(fileURLToPath(import.meta.url));
const BASE = process.env.PROTOTYPE_URL || 'http://localhost:9400';
const PROTO = process.env.PROTOTYPE_CONTAINER || 'reg-gibraltar-bb-prototype';
const DB_CONTAINER = PROTO.replace(/-prototype$/, '-db');

let failed = false;
const pass = (m) => console.log(`  PASS  ${m}`);
const fail = (m) => { console.log(`  FAIL  ${m}`); failed = true; };

const docker = (c, ...cmd) => execFileSync('docker', ['exec', c, ...cmd], { encoding: 'utf8' });

function findDbName() {
  const out = docker(DB_CONTAINER, 'mysql', '-uampersand', '-pampersand', '-N', '-e',
    "SELECT schema_name FROM information_schema.schemata " +
    "WHERE schema_name NOT IN ('information_schema','mysql','performance_schema','sys');").trim();
  const names = out.split('\n').map((s) => s.trim()).filter(Boolean);
  if (names.length !== 1) throw new Error(`Expected one application database, found: ${names.join(', ') || '(none)'}`);
  return names[0];
}
const DB_NAME = findDbName();
const mysql = (sql) => docker(DB_CONTAINER, 'mysql', '-uampersand', '-pampersand', '-N', '-e', sql, DB_NAME).trim();

// Model-driven table/column lookup from the compiler output.
const REL_JSON = JSON.parse(docker(PROTO, 'cat', '/var/www/backend/generics/relations.json'));
function relRows(label) {
  const r = REL_JSON.find((x) => x.label === label);
  if (!r) throw new Error(`relation '${label}' not in relations.json`);
  const { name: table, srcCol, tgtCol } = r.mysqlTable;
  return mysql(`SELECT CONCAT(\`${srcCol.name}\`, '|', \`${tgtCol.name}\`) FROM \`${table}\` ` +
    `WHERE \`${srcCol.name}\` IS NOT NULL AND \`${tgtCol.name}\` IS NOT NULL ORDER BY 1;`);
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
  try { body = await res.json(); } catch { /* ignore */ }
  return { status: res.status, committed: body.isCommitted === true, body };
}

const yamlBytes = readFileSync(join(DIR, 'GibraltarBB.yml'));

// Expected rows for a representative set of relations (from the Gibraltar source file).
const EXPECT = {
  certificateRequirement: 'Gibraltar|cert-gib-1',
  cropRequirement: 'Gibraltar|crop-gib-1',
  crop: 'crop-gib-1|Bloembollen',
  operator: 'rg-gib-1|AND',
  requirementType: 'req-gib-1|Other',
  sourceHeading: 'rg-gib-1|Producteisen\nrg-gib-1|Standaardeisen',
  appliesTo: 'cert-gib-1|Bloembollen. De invoervergunning kan worden aangevraagd bij: Trading Standards and Consumer Protection Dept., 12 Library Street, Gibraltar.',
  method: 'req-gib-1|Zie register Basisnormen Nederland voor Bloembollen en register Q-organismen',
  remarks: 'req-gib-1|Verwijzing naar generieke registers, geen zelfstandig checkbare eis in dit document.',
};

async function main() {
  console.log(`== GibraltarBB runtime import (db=${DB_NAME}) ==`);

  await reinstall();
  const r = await importFile(yamlBytes, 'GibraltarBB.yml');
  r.committed ? pass('YAML upload imported and committed (HTTP 200)')
              : fail(`YAML upload not committed (HTTP ${r.status})`);

  for (const [label, expected] of Object.entries(EXPECT)) {
    const actual = relRows(label);
    actual === expected
      ? pass(`${label} imported correctly (${actual.split('\n').length} row(s))`)
      : fail(`${label} mismatch\n    expected: ${JSON.stringify(expected)}\n    actual:   ${JSON.stringify(actual)}`);
  }

  console.log(failed ? '\n==== GIBRALTARBB IMPORT FAILED ====' : '\n==== GIBRALTARBB IMPORT PASSED ====');
  process.exit(failed ? 1 : 0);
}
main().catch((e) => { console.error(e); process.exit(1); });
