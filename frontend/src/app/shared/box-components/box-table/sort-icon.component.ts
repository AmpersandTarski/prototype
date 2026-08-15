import { Component, Input, OnDestroy, OnInit } from '@angular/core';
import { Subject, startWith, switchMap, takeUntil } from 'rxjs';
import { BoxTableComponent } from './box-table.component';
import { ObjectBase } from '../../objectBase.interface';

/**
 * Framework replacement for PrimeNG's `p-sortIcon`, for use in the
 * `boxTableHeader` template of a sortable BOX<TABLE>. See
 * SortableColumnDirective for why PrimeNG's own component cannot be used
 * there (declaration-site DI cannot reach the Table instance).
 */
@Component({
  selector: 'app-sort-icon',
  template: `<i class="p-sortable-column-icon pi {{ iconClass }}"></i>`,
})
export class SortIconComponent implements OnInit, OnDestroy {
  @Input({ required: true }) field!: string;

  iconClass = 'pi-sort-alt';

  private destroy$ = new Subject<void>();

  constructor(private boxTable: BoxTableComponent<ObjectBase, ObjectBase[]>) {}

  ngOnInit(): void {
    this.boxTable.table$
      .pipe(
        switchMap((table) =>
          table.tableService.sortSource$.pipe(startWith(null)),
        ),
        takeUntil(this.destroy$),
      )
      .subscribe(() => this.updateIcon());
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private updateIcon(): void {
    const order =
      this.boxTable.primengTable?.getSortMeta(this.field)?.order ?? 0;
    this.iconClass =
      order === 0
        ? 'pi-sort-alt'
        : order === 1
        ? 'pi-sort-amount-up-alt'
        : 'pi-sort-amount-down';
  }
}
