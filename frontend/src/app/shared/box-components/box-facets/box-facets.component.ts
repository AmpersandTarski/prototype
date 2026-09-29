import {
  Component,
  DoCheck,
  Input,
  OnChanges,
  OnInit,
  SimpleChanges,
  booleanAttribute,
  inject,
} from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntil } from 'rxjs';
import { BaseComponent } from '../../BaseComponent.class';
import { AmpersandInterfaceComponent } from '../../interfacing/ampersand-interface.class';
import { ObjectBase } from '../../objectBase.interface';
import { InterfacesJsonService } from '../../services/interfaces-json.service';
import {
  Bucket,
  EMPTY_KEY,
  FacetField,
  FacetKind,
  FacetOutcome,
  FacetSelection,
  FacetState,
  decodeState,
  encodeState,
  evaluate,
  fieldsOf,
  findField,
  itemsOf,
  isEmptySelection,
  chosenKinds,
  kindOf,
  locateBox,
  selectFields,
  sortBuckets,
  splitList,
  toggleDate,
  valuesAt,
} from './facet-engine';

/** A value list shows this many values until the user asks for more. */
const SHOWN_VALUES = 8;

interface DateNode {
  key: string;
  label: string;
  count: number;
  selected: boolean;
  children: DateNode[];
}

/** What the panel renders for one facet, recomputed on every change. */
interface FacetView {
  field: FacetField;
  kind: FacetKind;
  title: string;
  values: Bucket[];
  hidden: number;
  searchable: boolean;
  extent?: { min: number; max: number };
  dates: DateNode[];
}

interface Chip {
  facet: string;
  value: string;
  remove: () => void;
}

/**
 * BOX<FACETS>: a facet panel next to the table of BOX<TABLE> (DesignChoices OK-15 to OK-17).
 * The template (Box-FACETS.html) projects an app-box-table into this component; the
 * table keeps all rows as its data and shows `filtered` through `shownRows`, so every
 * TABLE annotation keeps working. The facet
 * logic lives in facet-engine.ts; this component holds the state, reads and
 * writes it in the URL, and renders the panel.
 */
@Component({
  selector: 'app-box-facets',
  templateUrl: './box-facets.component.html',
  styleUrls: ['./box-facets.component.scss'],
})
export class BoxFacetsComponent
  extends BaseComponent
  implements OnInit, OnChanges, DoCheck
{
  @Input() resource!: ObjectBase & { [key: string]: any };
  @Input({ required: true }) propertyName!: string;
  @Input({ required: true }) data!:
    | ObjectBase[]
    | ObjectBase
    | null
    | undefined;
  @Input({ required: true })
  interfaceComponent!: AmpersandInterfaceComponent<any>;
  @Input({ transform: booleanAttribute }) isRootBox = false;
  /** Which items are facets, and in which order: `facets="Status, Project.Owner"`. */
  @Input() facets?: string;
  /** Items that are facets but no table column: `facetOnly="Labels"`. */
  @Input() facetOnly?: string;
  /** The kind of facet chosen by the modeller: `facetKind="Date=date, Size=range"`. */
  @Input() facetKind?: string;

  /** The rows the table shows. */
  filtered: ObjectBase[] = [];
  total = 0;
  tree: FacetField[] = [];
  view = new Map<string, FacetView>();
  chips: Chip[] = [];
  state: FacetState = { search: '', selections: new Map() };
  panelOpen = true;

  private flat: FacetField[] = [];
  private topIds = new Set<string>();
  private kinds = new Map<string, FacetKind>();
  private chosen = new Map<string, FacetKind>();
  private labels = new Map<string, Map<string, string>>();
  private hiddenColumns = new Set<string>();
  /** Columns the user may edit; they stay, so an empty value can be filled in. */
  private editableColumns = new Set<string>();
  /** Columns that show one value (or none) in every row that passes; see refreshView. */
  private constantColumns = new Set<string>();
  /** Per facet, the value every passing row has, shown as checked in the panel. */
  private implied = new Map<string, string>();
  private expanded = new Set<string>();
  private childrenShown = new Set<string>();
  private valueFilters = new Map<string, string>();
  private ready = false;
  private paramPrefix = '';

  private lastSource?: ObjectBase[];
  private lastLength = -1;
  /** The source rows at the last refresh, to recognise a row that is new. */
  private known = new Set<ObjectBase>();
  /** A row created through the table stays visible until the selection changes. */
  private pinned = new Set<ObjectBase>();

  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute, { optional: true });
  private readonly interfacesJson = inject(InterfacesJsonService);

  async ngOnInit(): Promise<void> {
    this.paramPrefix = this.prefix();
    // An edit merges into the rows in place (syncWithServer); refresh the counts after it.
    this.interfaceComponent?.patched
      ?.pipe(takeUntil(this.destroy$))
      .subscribe(() => this.sourceChanged());
    // Column hiding depends on names only, so it is known before the types arrive.
    this.buildSchema(new Map());
    const types = await this.interfacesJson.conceptTypes();
    this.buildSchema(types);
    this.recomputeKinds();
    this.readUrl();
    this.ready = true;
    this.refresh();
  }

  ngOnChanges(changes: SimpleChanges): void {
    // Angular reuses this component when the enclosing row changes (a new route
    // parameter): the parameters and the selection belong to the new row.
    const change = changes['resource'];
    if (!change || change.firstChange || this.isRootBox) return;
    const prefix = this.prefix();
    if (prefix === this.paramPrefix) return;
    this.paramPrefix = prefix;
    if (!this.ready) return;
    this.pinned.clear();
    this.readUrl();
    this.refresh();
  }

  ngDoCheck(): void {
    const source = this.sourceRows();
    const replaced = source !== this.lastSource;
    if (!replaced && source.length === this.lastLength) return;
    // A row created through the table appears in front of the known rows: createItem
    // unshifts it into a list, and replaces the empty value of a UNI box. It stays
    // visible whatever the selection. A set of rows that shares no row with the known
    // ones is a new load, and pins nothing; neither does the very first set.
    if (this.lastSource !== undefined) {
      const real = source.filter(Boolean);
      const overlap = real.some((r) => this.known.has(r));
      const createdInEmpty = this.known.size === 0 && real.length === 1;
      if (!replaced || overlap || createdInEmpty) {
        for (const row of real) {
          if (this.known.has(row)) break;
          this.pinned.add(row);
        }
      }
    }
    this.lastSource = source;
    this.sourceChanged();
  }

  // --- the table ------------------------------------------------------------

  /**
   * Whether the table shows the column of this item: false for `facetOnly` items, and
   * false for a column whose value the panel already tells (see refreshView).
   */
  showColumn(name: string): boolean {
    return !this.hiddenColumns.has(name) && !this.constantColumns.has(name);
  }

  /** Whether every passing row has this value, so the panel shows it checked. */
  isImplied(f: FacetField, key: string): boolean {
    return this.implied.get(f.id) === key;
  }

  /**
   * Whether the panel shows this facet. A text facet on a column of the box itself
   * repeats the search field, which already searches every value of a row, so the
   * panel leaves it out; a text facet deeper in an OBJECT item stays.
   */
  showsFacet(f: FacetField, v: FacetView): boolean {
    return !(v.kind === 'text' && this.topIds.has(f.id));
  }

  // --- panel actions ----------------------------------------------------------

  setSearch(text: string): void {
    this.state.search = text ?? '';
    this.changed();
  }

  isSelected(f: FacetField, key: string): boolean {
    const sel = this.state.selections.get(f.id);
    return sel?.kind === 'values' && sel.keys.has(key);
  }

  toggleValue(f: FacetField, key: string): void {
    const sel = this.state.selections.get(f.id);
    const keys = new Set(sel?.kind === 'values' ? sel.keys : []);
    if (keys.has(key)) keys.delete(key);
    else keys.add(key);
    this.select(f, { kind: 'values', keys });
  }

  textOf(f: FacetField): string {
    const sel = this.state.selections.get(f.id);
    return sel?.kind === 'text' ? sel.text : '';
  }

  setText(f: FacetField, text: string): void {
    this.select(f, { kind: 'text', text: text ?? '' });
  }

  rangeOf(f: FacetField): { min?: number; max?: number } {
    const sel = this.state.selections.get(f.id);
    return sel?.kind === 'range' ? sel : {};
  }

  setRange(
    f: FacetField,
    end: 'min' | 'max',
    value: number | null | string,
  ): void {
    const n = value === null || value === '' ? undefined : Number(value);
    const range = {
      ...this.rangeOf(f),
      [end]: Number.isNaN(n) ? undefined : n,
    };
    this.select(f, { kind: 'range', min: range.min, max: range.max });
  }

  toggleDate(f: FacetField, key: string): void {
    const sel = this.state.selections.get(f.id);
    const prefixes = sel?.kind === 'date' ? sel.prefixes : new Set<string>();
    this.select(f, { kind: 'date', prefixes: toggleDate(prefixes, key) });
  }

  valueFilterOf(f: FacetField): string {
    return this.valueFilters.get(f.id) ?? '';
  }

  setValueFilter(f: FacetField, text: string): void {
    this.valueFilters.set(f.id, text ?? '');
    this.refreshView();
  }

  isExpanded(f: FacetField): boolean {
    return this.expanded.has(f.id);
  }

  toggleExpanded(f: FacetField): void {
    if (this.expanded.has(f.id)) this.expanded.delete(f.id);
    else this.expanded.add(f.id);
    this.refreshView();
  }

  childrenOpen(f: FacetField): boolean {
    return this.childrenShown.has(f.id) || this.hasSelectionBelow(f);
  }

  toggleChildren(f: FacetField): void {
    if (this.childrenShown.has(f.id)) this.childrenShown.delete(f.id);
    else this.childrenShown.add(f.id);
  }

  hasSelection(): boolean {
    return this.state.search.trim() !== '' || this.chips.length > 0;
  }

  clearAll(): void {
    this.state = { search: '', selections: new Map() };
    this.changed();
  }

  byId(_: number, f: FacetField): string {
    return f.id;
  }

  // --- internals ----------------------------------------------------------------

  private select(f: FacetField, sel: FacetSelection): void {
    if (isEmptySelection(sel)) this.state.selections.delete(f.id);
    else this.state.selections.set(f.id, sel);
    this.changed();
  }

  private sourceChanged(): void {
    const source = this.sourceRows();
    this.lastLength = source.length;
    this.known = new Set(source.filter(Boolean));
    this.recomputeKinds();
    this.refresh();
  }

  private changed(): void {
    this.pinned.clear();
    this.refresh();
    this.writeUrl();
  }

  /**
   * The prefix of this box's URL parameters. A nested box carries its parent atom: the
   * same box in two rows of an enclosing table must not share its parameters.
   */
  private prefix(): string {
    return this.isRootBox
      ? ''
      : `${this.propertyName}.${this.resource?._id_ ?? ''}.`;
  }

  private sourceRows(): ObjectBase[] {
    if (this.data === null || this.data === undefined) {
      this.lastSource ??= [];
      return this.lastSource;
    }
    return Array.isArray(this.data) ? this.data : [this.data];
  }

  private buildSchema(types: Map<string, string>): void {
    let interfaces: any[];
    try {
      interfaces = this.interfacesJson.getInterfaces();
    } catch {
      return;
    }
    const path = this.resource?._path_ ?? '';
    const interfaceName =
      this.interfaceComponent?.interfaceName ?? path.split('/')[3];
    const segments = this.isRootBox
      ? []
      : [...path.split('/').filter(Boolean).slice(4), this.propertyName];
    const box = interfaceName
      ? locateBox(interfaces, interfaceName, segments)
      : undefined;
    if (!box) {
      console.warn(
        `BOX<FACETS>: box '${this.propertyName}' not found in interfaces.json`,
      );
      return;
    }
    const all = fieldsOf(box, interfaces, types);
    const warn =
      this.ready || types.size > 0 ? (m: string) => console.warn(m) : undefined;
    this.tree = selectFields(all, this.facets, this.facetOnly, warn);
    this.topIds = new Set(this.tree.map((f) => f.id));
    this.flat = flatten(this.tree);
    this.chosen = chosenKinds(all, this.facetKind, warn);
    this.hiddenColumns = new Set(
      splitList(this.facetOnly)
        .map((entry) => findField(all, entry))
        .filter((f): f is FacetField => f !== undefined && f.path.length === 1)
        .map((f) => f.path[0]),
    );
    this.editableColumns = new Set(
      itemsOf(box, interfaces)
        .filter((item: any) => item.crud?.create || item.crud?.update)
        .map((item: any) => item.name),
    );
  }

  private recomputeKinds(): void {
    const rows = this.sourceRows().filter((r) => r !== null && r !== undefined);
    this.kinds = new Map(
      this.flat.map((f) => [f.id, this.chosen.get(f.id) ?? kindOf(f, rows)]),
    );
    // Labels of every value, so a chosen value keeps its label when no row shows it.
    this.labels = new Map(
      this.flat.map((f) => [
        f.id,
        new Map(
          rows
            .flatMap((r) => valuesAt(r, f.path, f.ttype))
            .map((v) => [v.key, v.label]),
        ),
      ]),
    );
  }

  private readUrl(): void {
    const params = this.route?.snapshot.queryParamMap;
    if (!params) return;
    this.state = decodeState(
      this.flat,
      this.kinds,
      (n) => params.getAll(n),
      this.paramPrefix,
    );
    // A selection deep in an OBJECT facet opens its parents.
    this.flat
      .filter((f) => this.state.selections.has(f.id))
      .forEach((f) => this.openParents(f));
  }

  private writeUrl(): void {
    if (!this.route) return;
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: encodeState(this.flat, this.state, this.paramPrefix),
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

  private refresh(): void {
    const source = this.sourceRows();
    this.total = source.filter((r) => r !== null && r !== undefined).length;
    let rows: ObjectBase[];
    if (!this.ready) {
      rows = source.filter((r) => r !== null && r !== undefined);
    } else {
      const outcome = evaluate(source, this.flat, this.kinds, this.state);
      const passing = new Set(outcome.rows);
      rows = source.filter((r) => r && (passing.has(r) || this.pinned.has(r)));
      this.outcome = outcome;
    }
    // Hand the table a new array only when the rows differ: every new array makes the
    // p-table sort again.
    if (!sameRows(rows, this.filtered)) this.filtered = rows;
    this.refreshView();
  }

  private outcome?: FacetOutcome;

  private refreshView(): void {
    const outcome = this.outcome;
    this.view = new Map();
    this.chips = [];
    if (!outcome) return;
    for (const f of this.flat) {
      const kind = this.kinds.get(f.id) ?? 'values';
      const buckets = outcome.buckets.get(f.id) ?? [];
      const title = this.topIds.has(f.id)
        ? f.label
        : f.labelPath[f.labelPath.length - 1];
      const v: FacetView = {
        field: f,
        kind,
        title,
        values: [],
        hidden: 0,
        searchable: false,
        dates: [],
      };
      if (kind === 'values') this.fillValues(v, buckets);
      if (kind === 'range') v.extent = outcome.extent.get(f.id);
      if (kind === 'date') v.dates = this.dateTree(f, buckets);
      this.view.set(f.id, v);
      this.addChips(f, title);
    }
    this.findConstantColumns();
  }

  /**
   * A column leaves the table when the panel already tells its value: at least two rows
   * pass, the column has a value or date facet, every passing row has the same value (or
   * none), and the user cannot edit the column. The facet then shows that value checked.
   * With the column Project gone after choosing Patents, the table keeps its width for
   * the columns that still differ.
   */
  private findConstantColumns(): void {
    this.constantColumns = new Set();
    this.implied = new Map();
    const rows = this.filtered.filter((r) => r !== null && r !== undefined);
    if (rows.length < 2) return;
    for (const f of this.tree) {
      const column = f.path[0];
      const kind = this.kinds.get(f.id);
      if (f.path.length !== 1 || f.isIdent) continue;
      if (kind !== 'values' && kind !== 'date') continue;
      if (this.editableColumns.has(column)) continue;
      const keysOf = (r: ObjectBase) =>
        valuesAt(r, f.path, f.ttype)
          .map((v) => v.key)
          .sort();
      const first = keysOf(rows[0]);
      const same = rows.every((r) => {
        const keys = keysOf(r);
        return keys.length === first.length && keys.every((k, i) => k === first[i]);
      });
      if (!same) continue;
      this.constantColumns.add(column);
      if (first.length === 1) this.implied.set(f.id, first[0]);
    }
  }

  private fillValues(v: FacetView, buckets: Bucket[]): void {
    const f = v.field;
    const sel = this.state.selections.get(f.id);
    const chosen = sel?.kind === 'values' ? sel.keys : new Set<string>();
    // A chosen value stays listed with count 0, so it can be unchosen.
    const all = [...buckets];
    for (const key of chosen) {
      if (!all.some((b) => b.key === key))
        all.push({ key, label: this.labelOf(f, key), count: 0 });
    }
    let list = sortBuckets(all, f.ttype);
    v.searchable = list.length > SHOWN_VALUES;
    const needle = this.valueFilterOf(f).toLowerCase();
    if (needle)
      list = list.filter((b) => b.label.toLowerCase().includes(needle));
    if (!this.expanded.has(f.id) && list.length > SHOWN_VALUES) {
      const shown = list.slice(0, SHOWN_VALUES);
      list
        .slice(SHOWN_VALUES)
        .forEach((b) => chosen.has(b.key) && shown.push(b));
      v.hidden = list.length - shown.length;
      list = shown;
    }
    v.values = list;
  }

  private dateTree(f: FacetField, buckets: Bucket[]): DateNode[] {
    const sel = this.state.selections.get(f.id);
    const chosen = sel?.kind === 'date' ? sel.prefixes : new Set<string>();
    const node = (b: Bucket): DateNode => ({
      key: b.key,
      label: dateLabel(b.key),
      count: b.count,
      selected: chosen.has(b.key),
      children: [],
    });
    const open = (key: string) => [...chosen].some((p) => p.startsWith(key));
    const level = (len: number, parent?: string) =>
      buckets
        .filter(
          (b) => b.key.length === len && (!parent || b.key.startsWith(parent)),
        )
        .sort((a, b) =>
          len === 4 ? b.key.localeCompare(a.key) : a.key.localeCompare(b.key),
        )
        .map(node);
    const years = level(4);
    for (const y of years) {
      if (!open(y.key)) continue;
      y.children = level(7, y.key);
      for (const m of y.children) {
        if (open(m.key)) m.children = level(10, m.key);
      }
    }
    const empty = buckets.find((b) => b.key === EMPTY_KEY);
    return empty ? [...years, node(empty)] : years;
  }

  private addChips(f: FacetField, title: string): void {
    const sel = this.state.selections.get(f.id);
    if (!sel || isEmptySelection(sel)) return;
    const chip = (value: string, remove: () => void) =>
      this.chips.push({ facet: title, value, remove });
    switch (sel.kind) {
      case 'values':
        sel.keys.forEach((k) =>
          chip(this.labelOf(f, k), () => this.toggleValue(f, k)),
        );
        break;
      case 'date':
        sel.prefixes.forEach((k) =>
          chip(dateLabel(k, true), () => this.toggleDate(f, k)),
        );
        break;
      case 'text':
        chip(`contains “${sel.text}”`, () => this.setText(f, ''));
        break;
      case 'range':
        chip(`${sel.min ?? '…'} – ${sel.max ?? '…'}`, () =>
          this.select(f, { kind: 'range' }),
        );
        break;
    }
  }

  private labelOf(f: FacetField, key: string): string {
    if (key === EMPTY_KEY) return '(empty)';
    return this.labels.get(f.id)?.get(key) ?? key;
  }

  private hasSelectionBelow(f: FacetField): boolean {
    return f.children.some(
      (c) => this.state.selections.has(c.id) || this.hasSelectionBelow(c),
    );
  }

  private openParents(f: FacetField): void {
    for (let i = 1; i < f.path.length; i++) {
      this.childrenShown.add(f.path.slice(0, i).join('/'));
    }
  }
}

function sameRows(a: ObjectBase[], b: ObjectBase[]): boolean {
  return a.length === b.length && a.every((row, i) => row === b[i]);
}

function flatten(fields: FacetField[]): FacetField[] {
  const seen = new Set<string>();
  const out: FacetField[] = [];
  const walk = (f: FacetField) => {
    if (seen.has(f.id)) return;
    seen.add(f.id);
    out.push(f);
    f.children.forEach(walk);
  };
  fields.forEach(walk);
  return out;
}

const MONTHS = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
];

/** "2026", "Sep" (or "Sep 2026" in full) and "28" (or "2026-09-28") for the date tree. */
function dateLabel(key: string, full = false): string {
  if (key === EMPTY_KEY) return '(empty)';
  if (key.length === 7) {
    const month = MONTHS[Number(key.slice(5, 7)) - 1] ?? key;
    return full ? `${month} ${key.slice(0, 4)}` : month;
  }
  if (key.length === 10) return full ? key : key.slice(8);
  return key;
}
