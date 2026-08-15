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
  @ViewChild('primengTable')
  set primengTable(table: Table | undefined) {
    this._primengTable = table;
    if (table) {
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

  @Input()
  sortOrder: 'asc' | 'desc' = 'asc';

  override ngOnInit(): void {
    super.ngOnInit();
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
