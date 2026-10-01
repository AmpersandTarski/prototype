import {
  Component,
  ContentChild,
  Input,
  OnInit,
  TemplateRef,
  ViewChild,
  booleanAttribute,
} from '@angular/core';
import { ReplaySubject } from 'rxjs';
import { ObjectBase } from '../../objectBase.interface';
import { BaseBoxComponent } from '../BaseBoxComponent.class';
import { BoxTableHeaderTemplateDirective } from './box-table-header-template.directive';
import { BoxTableRowTemplateDirective } from './box-table-row-template.directive';
import { Table } from 'primeng/table';

// NOTE: do not provide `Table` here via a useFactory that returns
// `boxTable.primengTable` (the old StackOverflow workaround for sorting from a
// projected header template). The header views are created BEFORE the
// ViewChild query resolves, so such a factory injects `undefined` into
// PrimeNG's sort directives, which then crash on `this.dt.tableService`.
// Sorting from the projected header is handled by SortableColumnDirective /
// SortIconComponent instead, which reach the table lazily via `table$`.
@Component({
  selector: 'app-box-table',
  templateUrl: './box-table.component.html',
  styleUrls: ['./box-table.component.css'],
})
export class BoxTableComponent<
    TItem extends ObjectBase,
    I extends ObjectBase | ObjectBase[],
  >
  extends BaseBoxComponent<TItem, I>
  implements OnInit
{
  @ContentChild(BoxTableHeaderTemplateDirective, { read: TemplateRef })
  headers?: TemplateRef<unknown>;
  @ContentChild(BoxTableRowTemplateDirective, { read: TemplateRef })
  rows?: TemplateRef<unknown>;

  private _primengTable?: Table;

  // The p-table instance for the sort helpers in the projected header
  // (SortableColumnDirective / SortIconComponent). Those are instantiated
  // before the ViewChild query below resolves, so they cannot take the table
  // synchronously; this ReplaySubject hands it to them once it appears.
  readonly table$ = new ReplaySubject<Table>(1);

  // #primengTable lives inside an *ngIf (hideBecauseEmpty), so a { static: true } query would be
  // undefined in ngOnInit and throw ("Cannot set properties of undefined"). Use a setter query
  // that configures the table whenever it appears — including after the *ngIf flips once data
  // arrives — so an initially-empty BOX<TABLE> renders instead of crashing.
  // Angular sets this query again whenever the view changes (a new number of rows, for
  // instance), with the same p-table. Only a new p-table is configured: configuring the same
  // one again put the sort back on `sortBy` and undid the sort the user had chosen.
  @ViewChild('primengTable')
  set primengTable(table: Table | undefined) {
    const isNew = table !== this._primengTable;
    this._primengTable = table;
    if (table && isNew) {
      this.configurePrimengTable(table);
      this.table$.next(table);
    }
  }
  get primengTable(): Table {
    return this._primengTable as Table;
  }

  @Input({ transform: booleanAttribute })
  sortable = false;

  @Input({ transform: booleanAttribute })
  noHeader = false;

  @Input()
  sortBy?: string;

  /**
   * The rows the table shows, when they differ from `data`. BOX<FACETS> passes its
   * filtered rows here and keeps `data` on all rows, so emptiness (`hideOnNoRecords`,
   * `canCreate()` on a UNI box) and create/delete keep working on the whole set.
   */
  @Input()
  shownRows?: TItem[];

  @Input()
  sortOrder: 'asc' | 'desc' = 'asc';

  /**
   * The `compact` annotation: the modeller's choice that this table starts in the dense
   * stand (DesignChoices OK-26). The user may switch; `dense` is what the table shows.
   */
  @Input({ transform: booleanAttribute })
  compact = false;

  /** Whether the table shows the dense stand: one line per row, under a header that stays. */
  dense = false;

  /** The rows the user opened in the dense stand, to read their full text. */
  private readonly opened = new WeakSet<object>();

  override ngOnInit(): void {
    super.ngOnInit();
    this.dense = this.storedDensity() ?? this.compact;
  }

  /**
   * Switches between the roomy and the dense stand, and remembers it for this table until
   * the tab closes. All tables share one item in session storage, `tableDensity`, so the page
   * on cookies and browser storage can name it.
   */
  toggleDense(): void {
    this.dense = !this.dense;
    const stands = this.storedStands();
    stands[this.densityKey()] = this.dense ? 'dense' : 'roomy';
    try {
      sessionStorage.setItem('tableDensity', JSON.stringify(stands));
    } catch {
      // without storage the choice lasts as long as the page
    }
  }

  isOpened(row: object): boolean {
    return this.opened.has(row);
  }

  /**
   * In the dense stand a click on a row, or Enter or Space on the focused row, shows its full
   * text, and a second one folds it again. A click on a link, a button or a field inside the row keeps its own meaning.
   */
  toggleRow(row: object, event: Event): void {
    if (!this.dense) return;
    if (event instanceof KeyboardEvent) {
      // Enter and Space do what a click does; every other key keeps its meaning.
      if (event.key !== 'Enter' && event.key !== ' ') return;
      if (event.target !== event.currentTarget) return;
      event.preventDefault();
    }
    const target = event.target as HTMLElement | null;
    if (target?.closest('a, button, input, select, textarea, .p-dropdown, app-ifcs-dropdown, .pi')) return;
    if (this.opened.has(row)) this.opened.delete(row);
    else this.opened.add(row);
  }

  /** One entry per table: the interface and the box in it. */
  private densityKey(): string {
    return `${this.interfaceComponent?.interfaceName ?? ''}.${this.propertyName ?? ''}`;
  }

  private storedStands(): Record<string, string> {
    try {
      const stands = JSON.parse(sessionStorage.getItem('tableDensity') ?? '{}');
      return stands !== null && typeof stands === 'object' ? stands : {};
    } catch {
      return {};
    }
  }

  private storedDensity(): boolean | null {
    const stored = this.storedStands()[this.densityKey()];
    return stored === undefined ? null : stored === 'dense';
  }

  private configurePrimengTable(table: Table): void {
    table.sortMode = 'multiple';

    // The defaultSortOrder is used when an unsorted column is sorted by user interaction
    table.defaultSortOrder = this.sortOrder === 'asc' ? 1 : -1;

    if (this.sortBy !== undefined) {
      table.multiSortMeta = [
        {
          field: this.sortBy,
          order: this.sortOrder === 'asc' ? 1 : -1,
        },
      ];
    }
    table.sortMultiple();
  }
}
