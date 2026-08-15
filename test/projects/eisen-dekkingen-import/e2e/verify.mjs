// Verify-harness voor eisen-dekkingen-import (SPEC.md §3).
//
// Toetst BEIDE importers op 100%:
//   Fase A (compile-time): na (her)installatie is model/populatie.xlsx via `INCLUDE` geladen
//     door de Haskell-importer → exporteer → vergelijk met de VERWACHTE paren over de
//     compile-partitie.
//   Fase B (runtime): upload e2e/data/runtime.xlsx naar POST /admin/import (PHP ExcelImporter)
//     → exporteer → vergelijk met de VERWACHTE paren over ALLE staal-ESE's.
//
// De verwachte paren worden hier ONAFHANKELIJK uit de ruwe rijen (sample-raw.json) afgeleid —
// een andere implementatie dan de Python-normalizer die de xlsx bouwt. Vergelijking is
// inhoud-gebaseerd (gereconstrueerde tupels), niet op de synthetische K/D/VK-id's, zodat een
// blad onder de verkeerde relatie of een overgeslagen blad door de mand valt.
//
// Env: PROTOTYPE_URL (default http://localhost:9400). Print "SCORE: <n>%"; exit 0 bij 100%.

import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = dirname(fileURLToPath(import.meta.url));
const BASE = process.env.PROTOTYPE_URL || 'http://localhost:9400';
const API = `${BASE}/api/v1`;

const raw = JSON.parse(readFileSync(join(DIR, 'sample-raw.json'), 'utf8'));
const partition = JSON.parse(readFileSync(join(DIR, 'data', 'partition.json'), 'utf8'));
const H = Object.fromEntries(raw.header.map((h, i) => [h, i]));

// ── kanonisatie ─────────────────────────────────────────────────────────────────────────
const NIL = '∅';
function canon(v) {
  if (v === null || v === undefined) return null;
  if (typeof v === 'number') return Number.isInteger(v) ? String(v) : String(v);
  const s = String(v).trim();
  return s === '' ? null : s;
}
const d10 = (v) => (v === null ? null : String(v).slice(0, 10)); // datum → YYYY-MM-DD
// Populatie-links vormen een VERZAMELING: elk feit (signatuur) bestaat óf niet, multipliciteit 1.
// Gedeelde-atoom-feiten (organismenaam, productnaam, …) worden vanuit meerdere rijen/ESE's
// afgeleid maar zijn één link; daarom set-semantiek (idempotent), geen multiset-telling.
const add = (m, s) => m.set(s, 1);
const j = (...xs) => xs.map((x) => (x === null ? NIL : x)).join('¦');

// ── VERWACHTE signaturen uit de ruwe rijen (onafhankelijke ont-kruising) ────────────────
function expectedSigs(eseSet) {
  const keep = new Set(eseSet);
  const sig = new Map();
  const g = (r, name) => canon(r[H[name]]);
  const byEse = new Map();
  for (const r of raw.rows) {
    const e = g(r, 'ESE_ESE_ID');
    if (e === null || !keep.has(e)) continue;
    if (!byEse.has(e)) byEse.set(e, []);
    byEse.get(e).push(r);
  }
  const vkTriple = (concept, taal, tekst) =>
    concept === null && taal === null && tekst === null ? null : j(concept, taal, tekst);

  for (const [e, rows] of byEse) {
    const r0 = rows[0];
    const groep = g(r0, 'PRODUCT_GROEP'), pnaam = g(r0, 'PRODUCT_NAAM');
    const pid = pnaam === null ? null : (groep === null ? pnaam : `${groep}␟${pnaam}`);
    const code = g(r0, 'LAND_CODE');
    const single = {
      product: pid, land: code, bak: g(r0, 'BAK_ID'), status: g(r0, 'ESE_STATUS'),
      hoofdeis: g(r0, 'HOOFDEIS'), eisNaam: g(r0, 'ESE_EIS_NAAM'),
      typeEis: g(r0, 'SES_TYPE_EIS_NAAM'), bron: g(r0, 'ESE_BRON'),
      interneMemo: g(r0, 'ESE_INTERNE_MEMO'), externeMemo: g(r0, 'ESE_EXTERNE_MEMO'),
      normOperator: g(r0, 'ESE_NORM_OPERATOR'), normWaarde: g(r0, 'ESE_NORM_WAARDE'),
      normEenheid: g(r0, 'ESE_NORM_EENHEID'),
    };
    for (const [rel, v] of Object.entries(single)) if (v !== null) add(sig, `E|${e}|${rel}|${v}`);
    for (const rel of ['beginDatum', 'eindDatum']) {
      const v = d10(g(r0, rel === 'beginDatum' ? 'ESE_BEGIN_DATUM' : 'ESE_EIND_DATUM'));
      if (v !== null) add(sig, `E|${e}|${rel}|${v}`);
    }
    if (pid !== null) {
      add(sig, `P|${pid}|productnaam|${pnaam}`);
      if (groep !== null) add(sig, `P|${pid}|productgroep|${groep}`);
    }
    if (code !== null) { const ln = g(r0, 'LAND_NAAM'); if (ln !== null) add(sig, `L|${code}|landnaam|${ln}`); }
    const dc = g(r0, 'DEKKING_CODE');
    if (dc !== null) for (const c of String(dc).split(';').map((x) => x.trim()).filter(Boolean)) add(sig, `E|${e}|dekkingCode|${c}`);

    const orgs = new Set(), verk = new Set(), ekk = new Set();
    const dkg = new Map(); // dkey → Set(vkTriple)
    for (const r of rows) {
      const oge = g(r, 'OGE_ID');
      if (oge !== null) { orgs.add(oge); const on = g(r, 'ESE_ORGANISME'); if (on !== null) add(sig, `O|${oge}|organismenaam|${on}`); }
      const vk = vkTriple(g(r, 'ESE_VERKLARINGSCONCEPT'), g(r, 'ESE_TAAL'), g(r, 'ESE_VERKLARINGSTEKST'));
      if (vk !== null) verk.add(vk);
      const kt = j(g(r, 'EKK_NAAM'), g(r, 'EKK_WAARDE_ORG'), g(r, 'EKK_WAARDE_VERTAALD'), g(r, 'EKK_STATUS'), d10(g(r, 'EKK_EIND_DATUM')));
      if ([g(r, 'EKK_NAAM'), g(r, 'EKK_WAARDE_ORG'), g(r, 'EKK_WAARDE_VERTAALD'), g(r, 'EKK_STATUS'), g(r, 'EKK_EIND_DATUM')].some((x) => x !== null)) ekk.add(kt);
      const scal = [g(r, 'SETNR'), g(r, 'ALT'), g(r, 'VOLGNR'), g(r, 'DKG_STATUS'), d10(g(r, 'DKG_EIND_DATUM')),
        g(r, 'DKG_TYPE_DEKKING'), g(r, 'DKG_CODE_INSPECTIE'), g(r, 'DKG_NAAM_DEKKING'), g(r, 'DKG_MODULE_INSPECTIE'),
        g(r, 'DKG_NORM_OPERATOR'), g(r, 'DKG_NORM_WAARDE'), g(r, 'DKG_NORM_EENHEID')];
      if (scal.some((x) => x !== null)) {
        const dkey = j(...scal);
        if (!dkg.has(dkey)) dkg.set(dkey, new Set());
        const dvk = vkTriple(g(r, 'DKG_VERKLARINGSCONCEPT'), g(r, 'DKG_VER_CONCEPT_TAAL'), g(r, 'DKG_VERKLARINGSTEKST'));
        if (dvk !== null) dkg.get(dkey).add(dvk);
      }
    }
    for (const o of orgs) add(sig, `E|${e}|organisme|${o}`);
    for (const v of verk) add(sig, `E|${e}|verklaring|${v}`);
    for (const k of ekk) add(sig, `K|${e}|${k}`);
    for (const [dkey, vks] of dkg) { add(sig, `D|${e}|${dkey}`); for (const v of vks) add(sig, `DV|${e}|${dkey}|${v}`); }
  }
  return sig;
}

// ── API ─────────────────────────────────────────────────────────────────────────────────
async function reinstall() {
  const r = await fetch(`${API}/admin/installer?ignoreInvariantRules=true`);
  if (!r.ok) throw new Error(`installer HTTP ${r.status}: ${await r.text()}`);
}
async function uploadExcel(path) {
  const buf = readFileSync(path);
  const fd = new FormData();
  fd.append('file', new Blob([buf]), path.split('/').pop());
  const r = await fetch(`${API}/admin/import`, { method: 'POST', body: fd });
  const body = await r.text();
  if (!r.ok) throw new Error(`import HTTP ${r.status}: ${body.slice(0, 800)}`);
  return body;
}
async function exportPopulation() {
  const r = await fetch(`${API}/admin/exporter/export/all`);
  if (!r.ok) throw new Error(`export HTTP ${r.status}: ${await r.text()}`);
  return r.json(); // { atoms:[{concept,atoms:[]}], links:[{relation:"n[S*T]", links:[{src,tgt}]}] }
}

// ── ACTUELE signaturen: reconstrueer uit de geëxporteerde populatie ─────────────────────
function actualSigs(pop) {
  const relRe = /^([^[]+)\[([^*]+)\*([^\]]+)\]$/;
  // linksBy["rel|Src"] = Map<src, tgt[]>
  const linksBy = new Map();
  for (const block of pop.links || []) {
    const m = relRe.exec(block.relation || '');
    if (!m) continue;
    const [, rel, src] = m;
    const key = `${rel}|${src.trim()}`;
    let map = linksBy.get(key);
    if (!map) { map = new Map(); linksBy.set(key, map); }
    for (const lk of block.links || []) {
      if (!map.has(lk.src)) map.set(lk.src, []);
      map.get(lk.src).push(lk.tgt);
    }
  }
  const one = (key, src, date = false) => {
    const t = (linksBy.get(key)?.get(src) || [])[0];
    if (t === undefined || t === null) return null;
    return date ? d10(canon(t)) : canon(t);
  };
  const many = (key, src) => (linksBy.get(key)?.get(src) || []).map(canon).filter((x) => x !== null);
  const atomsOf = (concept) => {
    const b = (pop.atoms || []).find((x) => x.concept === concept);
    return b ? (b.atoms || []) : [];
  };
  const sig = new Map();

  // Verklaring-tripels
  const vkTriple = new Map();
  for (const vk of atomsOf('Verklaring'))
    vkTriple.set(vk, j(one('verklaringconcept|Verklaring', vk), one('verklaringtaal|Verklaring', vk), one('verklaringtekst|Verklaring', vk)));

  // ESE
  const ESE_SINGLE = ['product', 'land', 'bak', 'status', 'hoofdeis', 'eisNaam', 'typeEis', 'bron', 'interneMemo', 'externeMemo', 'normOperator', 'normWaarde', 'normEenheid'];
  for (const e of atomsOf('ESE')) {
    for (const rel of ESE_SINGLE) { const v = one(`${rel}|ESE`, e); if (v !== null) add(sig, `E|${e}|${rel}|${v}`); }
    for (const rel of ['beginDatum', 'eindDatum']) { const v = one(`${rel}|ESE`, e, true); if (v !== null) add(sig, `E|${e}|${rel}|${v}`); }
    for (const o of many('organisme|ESE', e)) add(sig, `E|${e}|organisme|${o}`);
    for (const c of many('dekkingCode|ESE', e)) add(sig, `E|${e}|dekkingCode|${c}`);
    for (const vk of many('verklaring|ESE', e)) add(sig, `E|${e}|verklaring|${vkTriple.get(vk)}`);
  }
  // Product / Land / Organisme
  for (const p of atomsOf('Product')) { const n = one('productnaam|Product', p), g0 = one('productgroep|Product', p); if (n !== null) add(sig, `P|${p}|productnaam|${n}`); if (g0 !== null) add(sig, `P|${p}|productgroep|${g0}`); }
  for (const l of atomsOf('Land')) { const n = one('landnaam|Land', l); if (n !== null) add(sig, `L|${l}|landnaam|${n}`); }
  for (const o of atomsOf('Organisme')) { const n = one('organismenaam|Organisme', o); if (n !== null) add(sig, `O|${o}|organismenaam|${n}`); }
  // Kenmerk
  for (const k of atomsOf('Kenmerk')) {
    const e = one('kenmerkVanEse|Kenmerk', k);
    add(sig, `K|${e}|${j(one('kenmerksoort|Kenmerk', k), one('waardeOrigineel|Kenmerk', k), one('waardeVertaald|Kenmerk', k), one('status|Kenmerk', k), one('eindDatum|Kenmerk', k, true))}`);
  }
  // Dekking
  for (const dk of atomsOf('Dekking')) {
    const e = one('dekkingVanEse|Dekking', dk);
    const dkey = j(one('setnummer|Dekking', dk), one('alternatief|Dekking', dk), one('volgnummer|Dekking', dk),
      one('status|Dekking', dk), one('eindDatum|Dekking', dk, true), one('typeDekking|Dekking', dk),
      one('codeInspectie|Dekking', dk), one('naamDekking|Dekking', dk), one('moduleInspectie|Dekking', dk),
      one('normOperator|Dekking', dk), one('normWaarde|Dekking', dk), one('normEenheid|Dekking', dk));
    add(sig, `D|${e}|${dkey}`);
    for (const vk of many('verklaring|Dekking', dk)) add(sig, `DV|${e}|${dkey}|${vkTriple.get(vk)}`);
  }
  return sig;
}

function compare(exp, act) {
  let matched = 0, extra = 0;
  const missing = [];
  for (const [s, n] of exp) { const a = act.get(s) || 0; matched += Math.min(n, a); if (a < n) missing.push([s, n - a]); }
  for (const [s, n] of act) extra += Math.max(0, n - (exp.get(s) || 0));
  return { matched, expected: [...exp.values()].reduce((a, b) => a + b, 0), extra, missing };
}
function report(label, exp, act) {
  const { matched, expected, extra, missing } = compare(exp, act);
  const score = expected === 0 ? 0 : Math.floor((matched / expected) * 10000) / 100;
  console.log(`\n[${label}] expected=${expected} matched=${matched} extra=${extra} → ${score}%`);
  if (missing.length) {
    const byRel = new Map();
    for (const [s, n] of missing) { const rel = s.split('|').slice(0, 3).join('|'); byRel.set(rel, (byRel.get(rel) || 0) + n); }
    console.log(`  MISSING per pad (top 15):`);
    for (const [rel, n] of [...byRel].sort((a, b) => b[1] - a[1]).slice(0, 15)) console.log(`    ${n}× ${rel}`);
    console.log(`  voorbeeld ontbrekend: ${missing[0][0].slice(0, 200)}`);
  }
  return { ok: score === 100 && extra === 0, score, extra };
}

async function main() {
  console.log(`Verifying against ${BASE}`);
  // Fase A — compile-time (INCLUDE populatie.xlsx == compile-partitie, geladen bij install)
  await reinstall();
  const popA = await exportPopulation();
  const rA = report('compile-time (INCLUDE)', expectedSigs(partition.compile), actualSigs(popA));

  // Fase B — runtime (upload runtime-partitie); populatie is nu de UNIE
  await uploadExcel(join(DIR, 'data', 'runtime.xlsx'));
  const popB = await exportPopulation();
  const rB = report('runtime (upload)', expectedSigs(partition.all), actualSigs(popB));

  const ok = rA.ok && rB.ok;
  const worst = Math.min(rA.score, rB.score);
  console.log(`\nSCORE: ${worst}%  (compile ${rA.score}%, runtime ${rB.score}%, extras ${rA.extra}/${rB.extra})`);
  process.exit(ok ? 0 : 1);
}
main().catch((e) => { console.error(e); process.exit(1); });
