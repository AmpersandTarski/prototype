import {
  Directive,
  HostBinding,
  HostListener,
  Input,
  OnDestroy,
  OnInit,
} from '@angular/core';
import { Subject, startWith, switchMap, takeUntil } from 'rxjs';
import { Table } from 'primeng/table';
import { BoxTableComponent } from './box-table.component';
import { ObjectBase } from '../../objectBase.interface';

/**
 * Framework replacement for PrimeNG's `pSortableColumn`, for use in the
 * `boxTableHeader` template of a sortable BOX<TABLE> (see Box-TABLE.html).
 *
 * PrimeNG's own directive cannot be used there: the header ng-template is
 * declared inside <app-box-table> (outside the p-table element), so Angular's
 * declaration-site DI cannot reach the Table instance and `pSortableColumn`
 * crashes in its constructor ("can't access property tableService, this.dt is
 * undefined"). This directive instead injects BoxTableComponent — which IS in
 * scope at the declaration site — and reaches its p-table lazily via `table$`,
 * which also covers the p-table appearing only after the ViewChild query
 * resolves (or reappearing when the hideOnNoRecords *ngIf flips).
 */
@Directive({
  selector: '[appSortableColumn]',
})
export class SortableColumnDirective implements OnInit, OnDestroy {
  @Input({ alias: 'appSortableColumn', required: true }) field!: string;

  @HostBinding('class.p-sortable-column') readonly sortableClass = true;
  @HostBinding('attr.tabindex') readonly tabindex = 0;
  @HostBinding('class.p-highlight') sorted = false;
  @HostBinding('attr.aria-sort') ariaSort: 'ascending' | 'descending' | 'none' =
    'none';

  private table?: Table;
  private destroy$ = new Subject<void>();

  constructor(private boxTable: BoxTableComponent<ObjectBase, ObjectBase[]>) {}

  ngOnInit(): void {
    this.boxTable.table$
      .pipe(
        switchMap((table) => {
          this.table = table;
          return table.tableService.sortSource$.pipe(startWith(null));
        }),
        takeUntil(this.destroy$),
      )
      .subscribe(() => this.updateSortState());
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  @HostListener('click', ['$event'])
  @HostListener('keydown.enter', ['$event'])
  @HostListener('keydown.space', ['$event'])
  onSort(event: Event): void {
    this.table?.sort({ originalEvent: event, field: this.field });
    event.preventDefault();
  }

  private updateSortState(): void {
    const order = this.table?.getSortMeta(this.field)?.order ?? 0;
    this.sorted = order !== 0;
    this.ariaSort =
      order === 0 ? 'none' : order === 1 ? 'ascending' : 'descending';
  }
}
