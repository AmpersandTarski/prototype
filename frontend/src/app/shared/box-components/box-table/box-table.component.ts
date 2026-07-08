import {
  Component,
  ContentChild,
  Input,
  OnInit,
  TemplateRef,
  ViewChild,
  booleanAttribute,
} from '@angular/core';
import { ObjectBase } from '../../objectBase.interface';
import { BaseBoxComponent } from '../BaseBoxComponent.class';
import { BoxTableHeaderTemplateDirective } from './box-table-header-template.directive';
import { BoxTableRowTemplateDirective } from './box-table-row-template.directive';
import { Table, TableService } from 'primeng/table';

// Read why this is needed here: https://stackoverflow.com/questions/49988352/primeng-turbo-table-template-error-when-sorting
export function tableFactory<
  T extends ObjectBase,
  I extends ObjectBase | ObjectBase[],
>(boxTable: BoxTableComponent<T, I>) {
  return boxTable.primengTable;
}

@Component({
  selector: 'app-box-table',
  templateUrl: './box-table.component.html',
  styleUrls: ['./box-table.component.css'],
  providers: [
    TableService,
    { provide: Table, useFactory: tableFactory, deps: [BoxTableComponent] },
  ],
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

  // #primengTable lives inside an *ngIf (hideBecauseEmpty), so a { static: true } query would be
  // undefined in ngOnInit and throw ("Cannot set properties of undefined"). Use a setter query
  // that configures the table whenever it appears — including after the *ngIf flips once data
  // arrives — so an initially-empty BOX<TABLE> renders instead of crashing.
  @ViewChild('primengTable')
  set primengTable(table: Table | undefined) {
    this._primengTable = table;
    if (table) {
      this.configurePrimengTable(table);
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
