/**
 * The computational core of BOX<FACETS> (DesignChoices OK-15, OK-16): which facets a box has, what values
 * the rows carry for them, which rows survive a selection, and how many rows
 * each facet value would leave. Free of Angular, so it is unit-tested directly
 * (facet-engine.spec.ts) and reusable by other templates.
 *
 * A facet is a box item. Its technical type (TType) comes from interfaces.json
 * (the item's target concept) and concepts.json (that concept's type), and it
 * decides how the facet filters:
 *
 * | TType                                | kind of facet                          |
 * | ------------------------------------ | -------------------------------------- |
 * | ALPHANUMERIC, OBJECT                 | value list with counts; a text field   |
 * |                                      | when (nearly) every row has its own    |
 * | BIGALPHANUMERIC, HUGEALPHANUMERIC    | text field (substring match)           |
 * | BOOLEAN, a property relation [PROP]  | value list: yes / no                   |
 * | INTEGER, FLOAT                       | value list when few distinct values,   |
 * |                                      | otherwise a range (min..max)           |
 * | DATE, DATETIME                       | year > month > day, with counts        |
 * | PASSWORD, BINARY*, TYPEOFONE         | never a facet                          |
 *
 * An OBJECT item with a sub-box of its own carries its sub-items as child
 * facets, recursively: a facet on `Project` also offers the facets of the
 * items inside the Project box.
 */

export type TTypeName =
  | 'ALPHANUMERIC'
  | 'BIGALPHANUMERIC'
  | 'HUGEALPHANUMERIC'
  | 'PASSWORD'
  | 'BINARY'
  | 'BIGBINARY'
  | 'HUGEBINARY'
  | 'DATE'
  | 'DATETIME'
  | 'BOOLEAN'
  | 'INTEGER'
  | 'FLOAT'
  | 'OBJECT'
  | 'TYPEOFONE'
  /** concepts.json was not available; the kind is inferred from the values. */
  | 'UNKNOWN';

export type FacetKind = 'values' | 'text' | 'range' | 'date';

/** The TTypes that never become a facet: their values carry no meaning to filter on. */
export const NON_FACET_TTYPES: ReadonlySet<string> = new Set([
  'PASSWORD',
  'BINARY',
  'BIGBINARY',
  'HUGEBINARY',
  'TYPEOFONE',
]);

/** A value list with more distinct values than this becomes a range (numbers). */
export const MAX_NUMERIC_VALUES = 12;
/** A value list becomes a text field when it has more distinct values than this … */
export const MAX_LIST_VALUES = 40;
/** … and nearly every row carries a value of its own (distinct ≥ this share of the rows). */
export const UNIQUE_SHARE = 0.8;

/** Key of the "(empty)" bucket: rows without any value for the facet. */
export const EMPTY_KEY = '∅';

export interface FacetField {
  /** Unique within the box: the item names from the box row down, joined by '/'. */
  id: string;
  /** Row keys (escaped item names) from the box row down to this item. */
  path: string[];
  /** Human-readable item labels along the same path. */
  labelPath: string[];
  /** Label shown above the facet. */
  label: string;
  ttype: TTypeName;
  /** The item is the identity (`I`): its value is the row itself, so it is searched, not listed. */
  isIdent: boolean;
  /** The item's sub-items, when it is an OBJECT item with a box of its own. */
  children: FacetField[];
}

export interface FacetValue {
  key: string;
  label: string;
  /** Numeric value of an INTEGER/FLOAT, for range filters. */
  num?: number;
  /** yyyy-mm-dd of a DATE/DATETIME, for the date tree. */
  day?: string;
}

export type FacetSelection =
  | { kind: 'values'; keys: Set<string> }
  | { kind: 'text'; text: string }
  | { kind: 'range'; min?: number; max?: number }
  | { kind: 'date'; prefixes: Set<string> };

export interface FacetState {
  /** Free-text search over every value of a row. */
  search: string;
  /** Selection per facet id; a facet without an entry filters nothing. */
  selections: Map<string, FacetSelection>;
}

export interface Bucket {
  key: string;
  label: string;
  count: number;
}

export interface FacetOutcome {
  /** The rows that pass every facet and the search, in their original order. */
  rows: any[];
  /** Per facet id: the buckets (value list or date keys) counted over the rows
   *  that pass every *other* facet, so a facet never zeroes its own alternatives. */
  buckets: Map<string, Bucket[]>;
  /** Per range facet id: the smallest and largest value among those rows. */
  extent: Map<string, { min: number; max: number }>;
}

// ---------------------------------------------------------------------------
// Schema: from interfaces.json to facet fields
// ---------------------------------------------------------------------------

/** The items of a box node, following an inlined `INTERFACE <name>` reference. */
export function itemsOf(node: any, interfaces: any[]): any[] {
  const sub = node?.subinterfaces;
  if (!sub) return [];
  if (sub.refSubInterfaceName) {
    if (sub.refIsLinkTo) return [];
    const ref = interfaces.find((i) => i.name === sub.refSubInterfaceName);
    return ref?.ifcObject?.subinterfaces?.ifcObjects ?? [];
  }
  return sub.ifcObjects ?? [];
}

/**
 * The interfaces.json node of the box at `segments` below the top-level
 * interface. Segments that name no item are runtime atom ids and are skipped,
 * as InterfacesJsonService.findSubObject does.
 */
export function locateBox(
  interfaces: any[],
  interfaceName: string,
  segments: string[],
): any | undefined {
  let node = interfaces.find((i) => i.name === interfaceName)?.ifcObject;
  for (const segment of segments) {
    if (!node) return undefined;
    const next = itemsOf(node, interfaces).find((o: any) => o.name === segment);
    if (next) node = next;
  }
  return node;
}

/** Nesting depth at which child facets stop; a recursive interface reference ends here. */
const MAX_DEPTH = 4;

/** Facet fields for the items of a box node, with child facets for OBJECT items. */
export function fieldsOf(
  box: any,
  interfaces: any[],
  conceptTypes: Map<string, string>,
  parent: Pick<FacetField, 'path' | 'labelPath'> = { path: [], labelPath: [] },
  depth = 0,
): FacetField[] {
  if (depth >= MAX_DEPTH) return [];
  return itemsOf(box, interfaces)
    .filter((item: any) => item.type !== 'ObjText' && item.expr)
    .map((item: any) => {
      const path = [...parent.path, item.name];
      const labelPath = [...parent.labelPath, item.label ?? item.name];
      // concepts.json gives the singleton concept ONE the type OBJECT.
      const ttype = (
        item.expr.tgtConceptName === 'ONE'
          ? 'TYPEOFONE'
          : conceptTypes.get(item.expr.tgtConceptName) ?? 'UNKNOWN'
      ) as TTypeName;
      const field: FacetField = {
        id: path.join('/'),
        path,
        labelPath,
        label: labelPath.join(' › '),
        ttype,
        isIdent: item.expr.isIdent === true,
        children: [],
      };
      if (ttype === 'OBJECT' || ttype === 'UNKNOWN') {
        field.children = fieldsOf(
          item,
          interfaces,
          conceptTypes,
          field,
          depth + 1,
        );
      }
      return field;
    });
}

/**
 * The facets a box shows, from the `facets` annotation: a comma-separated list
 * of item labels (or names), where `A.B` names item B inside the box of item A.
 * Without the annotation every item is a facet. `facetOnly` items are facets
 * too, appended when `facets` does not name them. Items of a non-facet TType
 * are dropped; `warn` reports the ones that were named explicitly.
 */
export function selectFields(
  all: FacetField[],
  facets: string | undefined,
  facetOnly: string | undefined,
  warn: (msg: string) => void = () => undefined,
): FacetField[] {
  const named = [...splitList(facets), ...splitList(facetOnly)];
  const explicit = splitList(facets).length > 0;
  let chosen: FacetField[];
  if (!explicit) {
    chosen = [...all];
    for (const entry of splitList(facetOnly)) {
      const f = findField(all, entry);
      if (!f) warn(`BOX<FACETS>: no box item '${entry}'`);
      else if (!chosen.includes(f)) chosen.push(f);
    }
  } else {
    chosen = [];
    for (const entry of named) {
      const f = findField(all, entry);
      if (!f) {
        warn(`BOX<FACETS>: no box item '${entry}'`);
      } else if (!chosen.includes(f)) {
        chosen.push(f);
      }
    }
  }
  // A chosen item inside another chosen item already appears as its child facet.
  const inside = (f: FacetField, g: FacetField) =>
    g !== f &&
    g.path.length < f.path.length &&
    g.path.every((p, i) => p === f.path[i]);
  return chosen
    .filter((f) => !chosen.some((g) => inside(f, g)))
    .map((f) => pruneNonFacets(f, named, warn))
    .filter((f): f is FacetField => f !== undefined);
}

function pruneNonFacets(
  f: FacetField,
  named: string[],
  warn: (msg: string) => void,
): FacetField | undefined {
  if (NON_FACET_TTYPES.has(f.ttype)) {
    if (named.some((n) => matchesEntry(f, n))) {
      warn(
        `BOX<FACETS>: '${f.label}' has type ${f.ttype}, which is never a facet`,
      );
    }
    return undefined;
  }
  return {
    ...f,
    children: f.children
      .map((c) => pruneNonFacets(c, named, warn))
      .filter((c): c is FacetField => c !== undefined),
  };
}

export function splitList(list: string | undefined): string[] {
  return (list ?? '')
    .split(',')
    .map((s) => s.trim())
    .filter((s) => s !== '');
}

function matchesEntry(f: FacetField, entry: string): boolean {
  const parts = entry.split('.').map((s) => s.trim());
  return (
    parts.length === f.path.length &&
    parts.every((p, i) => p === f.labelPath[i] || p === f.path[i])
  );
}

export function findField(
  all: FacetField[],
  entry: string,
): FacetField | undefined {
  for (const f of all) {
    if (matchesEntry(f, entry)) return f;
    const inner = findField(f.children, entry);
    if (inner) return inner;
  }
  return undefined;
}

// ---------------------------------------------------------------------------
// Values
// ---------------------------------------------------------------------------

/** Every value a row carries along `path`, flattened over multi-valued items. */
export function valuesAt(
  row: any,
  path: string[],
  ttype: TTypeName,
): FacetValue[] {
  const out: FacetValue[] = [];
  const walk = (value: any, rest: string[]): void => {
    if (value === null || value === undefined) return;
    if (Array.isArray(value)) {
      value.forEach((v) => walk(v, rest));
      return;
    }
    if (rest.length > 0) {
      if (typeof value === 'object') walk(value[rest[0]], rest.slice(1));
      return;
    }
    out.push(toFacetValue(value, ttype));
  };
  walk(row?.[path[0]], path.slice(1));
  return out;
}

export function toFacetValue(value: any, ttype: TTypeName): FacetValue {
  if (typeof value === 'boolean') {
    return { key: String(value), label: value ? 'yes' : 'no' };
  }
  if (typeof value === 'number') {
    return { key: String(value), label: String(value), num: value };
  }
  if (typeof value === 'object') {
    const key = String(value._id_ ?? '');
    return {
      key,
      label: value._label_ ? String(value._label_) : decodeId(key),
    };
  }
  const s = String(value);
  const v: FacetValue = { key: s, label: s };
  if (
    ttype === 'DATE' ||
    ttype === 'DATETIME' ||
    (ttype === 'UNKNOWN' && looksLikeDate(s))
  ) {
    v.day = s.slice(0, 10);
  }
  if (ttype === 'INTEGER' || ttype === 'FLOAT') {
    const n = Number(s);
    if (!Number.isNaN(n)) v.num = n;
  }
  return v;
}

function decodeId(id: string): string {
  try {
    return decodeURIComponent(id);
  } catch {
    return id;
  }
}

function looksLikeDate(s: string): boolean {
  return /^\d{4}-\d{2}-\d{2}(T|$)/.test(s);
}

/** The kind of filter a facet offers, from its TType and the values the rows carry. */
export function kindOf(field: FacetField, rows: any[]): FacetKind {
  if (field.isIdent) return 'text';
  const values = rows.flatMap((r) => valuesAt(r, field.path, field.ttype));
  if (
    values.length > 0 &&
    values.every((v) => v.key === 'true' || v.key === 'false')
  ) {
    // BOOLEAN, or a property relation [PROP], which the frontend receives as a boolean.
    return 'values';
  }
  switch (field.ttype) {
    case 'BIGALPHANUMERIC':
    case 'HUGEALPHANUMERIC':
      return 'text';
    case 'DATE':
    case 'DATETIME':
      return 'date';
    case 'INTEGER':
    case 'FLOAT':
      return distinct(values) <= MAX_NUMERIC_VALUES ? 'values' : 'range';
    case 'UNKNOWN':
      if (values.length > 0 && values.every((v) => v.day !== undefined))
        return 'date';
      if (values.length > 0 && values.every((v) => v.num !== undefined)) {
        return distinct(values) <= MAX_NUMERIC_VALUES ? 'values' : 'range';
      }
      return listOrText(values, rows);
    default:
      return listOrText(values, rows);
  }
}

function distinct(values: FacetValue[]): number {
  return new Set(values.map((v) => v.key)).size;
}

function listOrText(values: FacetValue[], rows: any[]): FacetKind {
  const n = distinct(values);
  return n > MAX_LIST_VALUES && n >= UNIQUE_SHARE * rows.length
    ? 'text'
    : 'values';
}

// ---------------------------------------------------------------------------
// Filtering and counting
// ---------------------------------------------------------------------------

/** Whether a row passes one facet's selection. */
export function passes(
  row: any,
  field: FacetField,
  sel: FacetSelection | undefined,
): boolean {
  if (!sel || isEmptySelection(sel)) return true;
  const values = valuesAt(row, field.path, field.ttype);
  switch (sel.kind) {
    case 'values':
      return values.length === 0
        ? sel.keys.has(EMPTY_KEY)
        : values.some((v) => sel.keys.has(v.key));
    case 'text': {
      const needle = sel.text.toLowerCase();
      return values.some((v) => v.label.toLowerCase().includes(needle));
    }
    case 'range':
      return values.some(
        (v) =>
          v.num !== undefined &&
          (sel.min === undefined || v.num >= sel.min) &&
          (sel.max === undefined || v.num <= sel.max),
      );
    case 'date':
      return values.length === 0
        ? sel.prefixes.has(EMPTY_KEY)
        : values.some(
            (v) =>
              v.day !== undefined &&
              [...sel.prefixes].some((p) => v.day!.startsWith(p)),
          );
  }
}

export function isEmptySelection(sel: FacetSelection): boolean {
  switch (sel.kind) {
    case 'values':
      return sel.keys.size === 0;
    case 'text':
      return sel.text.trim() === '';
    case 'range':
      return sel.min === undefined && sel.max === undefined;
    case 'date':
      return sel.prefixes.size === 0;
  }
}

/** All human-readable text of a row, lower-cased, for the free-text search. */
export function rowText(row: any): string {
  if (row === null || typeof row !== 'object')
    return String(row ?? '').toLowerCase();
  const parts: string[] = [];
  const walk = (v: any): void => {
    if (v === null || v === undefined) return;
    if (Array.isArray(v)) return v.forEach(walk);
    if (typeof v === 'object') {
      if (v._label_ !== undefined) parts.push(String(v._label_));
      for (const [k, x] of Object.entries(v)) {
        if (!k.startsWith('_')) walk(x);
      }
      return;
    }
    if (typeof v !== 'boolean') parts.push(String(v));
  };
  walk(row);
  // No cache: an edit merges into the row in place, so a cached text would go stale.
  return parts.join('\n').toLowerCase();
}

/**
 * Filter the rows and count the buckets of every facet. A row that fails no
 * facet counts for all facets; a row that fails exactly one facet counts only
 * for that facet (it would pass if that facet's selection changed); a row that
 * fails two or more counts nowhere.
 */
export function evaluate(
  rows: any[],
  fields: FacetField[],
  kinds: Map<string, FacetKind>,
  state: FacetState,
): FacetOutcome {
  const needle = state.search.trim().toLowerCase();
  const passing: any[] = [];
  const counters = new Map<string, Map<string, Bucket>>();
  const extent = new Map<string, { min: number; max: number }>();
  fields.forEach((f) => counters.set(f.id, new Map()));

  for (const row of rows) {
    if (row === null || row === undefined) continue;
    if (needle !== '' && !rowText(row).includes(needle)) continue;
    const failing: FacetField[] = [];
    for (const f of fields) {
      if (!passes(row, f, state.selections.get(f.id))) {
        failing.push(f);
        if (failing.length > 1) break;
      }
    }
    if (failing.length === 0) passing.push(row);
    if (failing.length > 1) continue;
    const countFor = failing.length === 0 ? fields : failing;
    for (const f of countFor) {
      count(row, f, kinds.get(f.id) ?? 'values', counters.get(f.id)!, extent);
    }
  }

  const buckets = new Map<string, Bucket[]>();
  for (const [id, m] of counters) buckets.set(id, [...m.values()]);
  return { rows: passing, buckets, extent };
}

function count(
  row: any,
  f: FacetField,
  kind: FacetKind,
  m: Map<string, Bucket>,
  extent: Map<string, { min: number; max: number }>,
): void {
  const values = valuesAt(row, f.path, f.ttype);
  const add = (key: string, label: string) => {
    const b = m.get(key);
    if (b) b.count++;
    else m.set(key, { key, label, count: 1 });
  };
  if (kind === 'range') {
    for (const v of values) {
      if (v.num === undefined) continue;
      const e = extent.get(f.id);
      if (!e) extent.set(f.id, { min: v.num, max: v.num });
      else {
        e.min = Math.min(e.min, v.num);
        e.max = Math.max(e.max, v.num);
      }
    }
    return;
  }
  if (kind === 'text') return;
  if (values.length === 0) {
    add(EMPTY_KEY, '(empty)');
    return;
  }
  const seen = new Set<string>();
  for (const v of values) {
    const keys: [string, string][] =
      kind === 'date'
        ? v.day
          ? [
              [v.day.slice(0, 4), v.day.slice(0, 4)],
              [v.day.slice(0, 7), v.day.slice(0, 7)],
              [v.day, v.day],
            ]
          : []
        : [[v.key, v.label]];
    for (const [key, label] of keys) {
      if (seen.has(key)) continue; // a row counts once per bucket
      seen.add(key);
      add(key, label);
    }
  }
}

/**
 * Value-list buckets in display order, "(empty)" last: numbers by value, so a
 * scale reads as a scale; everything else with the most rows first.
 */
export function sortBuckets(buckets: Bucket[], ttype?: TTypeName): Bucket[] {
  const numeric = ttype === 'INTEGER' || ttype === 'FLOAT';
  return [...buckets].sort((a, b) => {
    if (a.key === EMPTY_KEY) return 1;
    if (b.key === EMPTY_KEY) return -1;
    if (numeric) return Number(a.key) - Number(b.key);
    return b.count - a.count || a.label.localeCompare(b.label);
  });
}

/**
 * Select or deselect a date bucket. Choosing a month replaces its year, and a
 * day its month, so the narrower choice takes effect; choosing a year or month
 * drops the narrower choices beneath it.
 */
export function toggleDate(prefixes: Set<string>, key: string): Set<string> {
  const next = new Set(prefixes);
  if (next.has(key)) {
    next.delete(key);
    return next;
  }
  for (const p of [...next]) {
    if (key.startsWith(p) || p.startsWith(key)) next.delete(p);
  }
  next.add(key);
  return next;
}

// ---------------------------------------------------------------------------
// URL state
// ---------------------------------------------------------------------------

/**
 * The query parameters that carry a state: `<prefix>q` for the search and
 * `<prefix>f.<Label>.<Label>` per facet, with one parameter value per chosen
 * value (a range as `min..max`). Labels keep the URL readable and bookmarkable.
 */
export function encodeState(
  fields: FacetField[],
  state: FacetState,
  prefix: string,
): Record<string, string[] | null> {
  const params: Record<string, string[] | null> = {};
  params[`${prefix}q`] = state.search.trim() ? [state.search.trim()] : null;
  for (const f of fields) {
    const sel = state.selections.get(f.id);
    params[paramName(f, prefix)] =
      sel && !isEmptySelection(sel) ? encodeSelection(sel) : null;
  }
  return params;
}

export function decodeState(
  fields: FacetField[],
  kinds: Map<string, FacetKind>,
  params: (name: string) => string[],
  prefix: string,
): FacetState {
  const selections = new Map<string, FacetSelection>();
  for (const f of fields) {
    const values = params(paramName(f, prefix));
    if (values.length === 0) continue;
    const kind = kinds.get(f.id) ?? 'values';
    const numeric = f.ttype === 'INTEGER' || f.ttype === 'FLOAT';
    selections.set(f.id, decodeSelection(kind, values, numeric));
  }
  return { search: params(`${prefix}q`)[0] ?? '', selections };
}

export function paramName(f: FacetField, prefix: string): string {
  // A label with a period would read as a path; its item name (an escaped identifier) cannot.
  const segments = f.labelPath.map((label, i) =>
    label.includes('.') ? f.path[i] : label,
  );
  return `${prefix}f.${segments.join('.')}`;
}

function encodeSelection(sel: FacetSelection): string[] {
  switch (sel.kind) {
    case 'values':
      return [...sel.keys];
    case 'text':
      return [sel.text];
    case 'range':
      return [`${sel.min ?? ''}..${sel.max ?? ''}`];
    case 'date':
      return [...sel.prefixes];
  }
}

/** `min..max`, either end empty or a number: the form a range has in the URL. */
function isRange(v: string): boolean {
  const ends = v.split('..');
  return (
    ends.length === 2 && ends.every((e) => e === '' || !Number.isNaN(Number(e)))
  );
}

/**
 * A bookmark outlives the data: a number facet can turn from a range into a value list
 * (or back) when the rows change. The form of the value decides, so the bookmark keeps
 * its meaning instead of matching no row.
 */
function decodeSelection(
  kind: FacetKind,
  values: string[],
  numeric: boolean,
): FacetSelection {
  if (numeric) {
    kind = values.length === 1 && isRange(values[0]) ? 'range' : 'values';
  }
  switch (kind) {
    case 'values':
      return { kind, keys: new Set(values) };
    case 'text':
      return { kind, text: values[0] ?? '' };
    case 'range': {
      const [lo, hi] = (values[0] ?? '').split('..');
      const num = (s: string | undefined) =>
        s === undefined || s === '' || Number.isNaN(Number(s))
          ? undefined
          : Number(s);
      return { kind, min: num(lo), max: num(hi) };
    }
    case 'date':
      return { kind, prefixes: new Set(values) };
  }
}
