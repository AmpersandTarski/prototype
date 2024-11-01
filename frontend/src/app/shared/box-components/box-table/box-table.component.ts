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

  @ViewChild('primengTable', { static: true })
  public primengTable: Table;

  @Input({ transform: booleanAttribute })
  sortable = false;

  @Input()
  sortBy?: string;

  @Input()
  sortOrder: 'asc' | 'desc' = 'asc';

  override ngOnInit(): void {
    super.ngOnInit();

    this.primengTable.sortMode = 'multiple';

    // The defaultSortOrder is used when an unsorted column is sorted by user interaction
    this.primengTable.defaultSortOrder = this.sortOrder === 'asc' ? 1 : -1;

    if (this.sortBy !== undefined) {
      this.primengTable.multiSortMeta = [
        {
          field: this.sortBy,
          order: this.sortOrder === 'asc' ? 1 : -1,
        },
      ];
    }
    this.primengTable.sortMultiple();
  }
}
