import {
  Component,
  computed,
  Input,
  OnInit,
  Signal,
  signal,
  ViewChild,
} from '@angular/core';
import { takeUntil, tap, switchMap, map } from 'rxjs/operators';
import { BehaviorSubject, of } from 'rxjs';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { Dropdown } from 'primeng/dropdown';
import { isObject } from '../../helper/deepmerge';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-object',
  templateUrl: './atomic-object.component.html',
  styleUrls: ['./atomic-object.component.scss'],
})
export class AtomicObjectComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<ObjectBase, I>
  implements OnInit
{
  @Input() public placeholder!: string;
  @Input() public tgtResourceType!: string;
  
  private _selectOptions?: ObjectBase[] | ObjectBase;
  private selectOptions$ = new BehaviorSubject<ObjectBase[] | ObjectBase | undefined>(undefined);
  
  @Input() set selectOptions(value: ObjectBase[] | ObjectBase | undefined) {
    this._selectOptions = value;
    this.selectOptions$.next(value);
  }
  get selectOptions(): ObjectBase[] | ObjectBase | undefined {
    return this._selectOptions;
  }

  // stores all options for the dropdown
  public allOptions = signal<ObjectBase[]>([]);

  // in the uni case the input can be the selected object,
  // null when no object is selected, or a string when the user is typing
  public uniValue = signal<ObjectBase | string | null>(null);

  // used for filtering non uni selectable options
  public filterValue = signal<string>('');

  public isObject = isObject;

  // includes selected id and applies search filter
  public uniSelectableOptions: Signal<ObjectBase[]> = computed(() => {
    const allOptions = this.allOptions();
    const uniValue = this.uniValue();
    if (typeof uniValue !== 'string' || uniValue.trim().length === 0) {
      return allOptions;
    }

    const lowerCaseFilterValue = uniValue.trim().toLowerCase();
    return allOptions.filter((option) =>
      option._label_.toLowerCase().includes(lowerCaseFilterValue),
    );
  });

  // excludes selected ids and applies search filter
  public nonUniSelectableOptions: Signal<ObjectBase[]> = computed(() => {
    // For filtered dropdowns (when selectOptions is provided), show all filtered options
    if (this.selectOptions) {
      const allOptions = this.allOptions();

      // check if a filter is applied
      const lowerCaseFilterValue = this.filterValue().trim().toLowerCase();
      const filterIsApplied = lowerCaseFilterValue.length !== 0;
      if (!filterIsApplied) {
        return allOptions;
      }

      // filter options by search term
      const searchFiltered = allOptions.filter((option) =>
        option._label_.toLowerCase().includes(lowerCaseFilterValue),
      );
      return searchFiltered;
    }

    // Original behavior for non-filtered dropdowns: exclude selected ids
    const selectedIds = this.selection().map((d: ObjectBase) => d._id_);
    const allOptionsWithoutSelected = this.allOptions().filter(
      (option) => !selectedIds.includes(option._id_),
    );

    // check if a filter is applied
    const lowerCaseFilterValue = this.filterValue().trim().toLowerCase();
    const filterIsApplied = lowerCaseFilterValue.length !== 0;
    if (!filterIsApplied) {
      return allOptionsWithoutSelected;
    }

    // filter options
    return allOptionsWithoutSelected.filter((option) =>
      option._label_.toLowerCase().includes(lowerCaseFilterValue),
    );
  });

  // used in the non uni case
  private selection = signal([] as ObjectBase[]);

  // to programmatically control the dropdown
  @ViewChild('dropdown') private dropdown: Dropdown;

  private getSelectOptionsObservable(selectOptions: ObjectBase[] | ObjectBase) {
    return of(selectOptions).pipe(
      map((selectOptions) => {
        const selectOptionsArray = Array.isArray(selectOptions) 
          ? selectOptions 
          : [selectOptions];
        
        // Filter the options based on the 'selectOptions' property
        return selectOptionsArray.filter((item: any) => {
          const shouldInclude = item.select === true;
          return shouldInclude;
        });
      })
    );
  }

  private getBackendDataObservable() {
    return this.interfaceComponent.fetchDropdownMenuData(`resource/${this.tgtResourceType}`);
  }

  override ngOnInit(): void {
    super.ngOnInit();

    // is there anything to choose from? Else just a list is displayed
    if (!(this.canUpdate() || this.selectOptions)) {
      return;
    }

    // Reactive chain: respond to selectOptions changes using BehaviorSubject
    this.selectOptions$.pipe(
      switchMap((selectOptions) => 
        selectOptions
          ? this.getSelectOptionsObservable(selectOptions)
          : this.canUpdate() 
            ? this.getBackendDataObservable()
            : of([])
      ),
      tap((optionsToDisplay: ObjectBase[]) => {
        // Set initial options and signals
        this.allOptions.set(optionsToDisplay);
        
        // Set selected option(s) signals
        if (this.isUni) {
          this.uniValue.set(this.resource[this.propertyName] ?? null);
        } else {
          this.selection.set(this.data);
        }
      }),
      switchMap(() => 
        this.interfaceComponent.patched.pipe(
          map(() => {
            // For selectOptions case, return updated options
            if (this.selectOptions) {
              const updatedSelectOptionsArray = Array.isArray(this.selectOptions) 
                ? this.selectOptions 
                : [this.selectOptions];
              return [...updatedSelectOptionsArray]; // spread to trigger change
            }
            // For canUpdate case, return current options with spread to trigger change
            return [...this.allOptions()];
          })
        )
      ),
      takeUntil(this.destroy$)
    ).subscribe((updatedOptions: ObjectBase[]) => {
      this.allOptions.set(updatedOptions);
    });
  }

  // used in uni case
  public update(): void {
    const uniValue = this.uniValue();
    if (!uniValue || typeof uniValue !== 'object') {
      return; // we need an object
    }

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'replace',
          path: this.propertyName,
          value: uniValue._id_,
        },
      ])
      .subscribe(() => {
        this.uniValue.set(this.resource[this.propertyName]);
      });
  }

  // used in non uni case
  public add() {
    if (!this.newValue) {
      return;
    }

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'add',
          path: this.propertyName,
          value: this.newValue._id_,
        },
      ])
      .subscribe(() => {
        this.newValue = undefined; // reset newValue
        this.selection.set([...this.data]); // spread to trigger change
      });
  }

  public isAllowedToCreate(value: ObjectBase | string): boolean {
    if (typeof value !== 'string') {
      return false; // already an object
    }

    const trimmedValue = value.trim();
    return (
      this.canCreate() &&
      trimmedValue.length > 0 &&
      !this.existsInOptions(trimmedValue)
    );
  }

  private existsInOptions(value: string): boolean {
    const lowerCaseFilterValue = value.toLowerCase();
    const lowerCaseIds = this.allOptions().map((option) =>
      option._id_.toLowerCase(),
    );

    return lowerCaseIds.includes(lowerCaseFilterValue);
  }

  public handleFilterInput(
    filterValue: ObjectBase | string,
    e: KeyboardEvent,
  ): void {
    // add filter value as item on enter
    if (e.code === 'Enter') {
      this.createAndAdd(filterValue);
    }
  }

  public createAndAdd(value: ObjectBase | string): void {
    if (typeof value !== 'string') {
      return; // could be an object, but we need a string
    }

    const trimmedValue = value.trim();
    if (!this.isAllowedToCreate(trimmedValue)) {
      return;
    }

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'add',
          path: this.propertyName,
          value: trimmedValue,
        },
      ])
      .subscribe(() => {
        // hide dropdown
        this.dropdown.hide();

        // grab new item
        // clone to disconnect from reactive object,
        // otherwise previously added uni value will also be updated
        const newItem = {
          ...this.data.find((item) => item._id_ === trimmedValue)!,
        };

        // add new item to allOptions
        this.allOptions.update((options) =>
          // we spread to trigger change
          [...options, newItem].sort((a, b) => {
            // sort options by label
            if (a._label_ < b._label_) {
              return -1;
            } else if (a._label_ > b._label_) {
              return 1;
            } else {
              return 0;
            }
          }),
        );

        if (this.isUni) {
          this.uniValue.set(newItem); // set value
        } else {
          // clear filter
          this.filterValue.set('');

          // update selected options
          this.selection.set([...this.data]); // spread to trigger change
        }
      });
  }

  public remove(index = 0) {
    const id = this.data[index]._id_;

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'remove',
          path: `${this.propertyName}/${id}`,
        },
      ])
      .subscribe(() => {
        if (this.isUni) {
          this.uniValue.set(null);
        } else {
          this.selection.set([...this.data]); // spread to trigger change
        }
      });
  }

  public delete(index = 0) {
    if (!confirm('Delete?')) {
      return;
    }

    const id = this.data[index]._id_;

    this.interfaceComponent
      .delete(`${this.resource._path_}/${this.propertyName}/${id}`)
      .subscribe((x) => {
        if (x.isCommitted && x.invariantRulesHold) {
          // delete does not update the data (as patch does),
          // so we need to do it here
          if (this.isUni) {
            // clear uni value
            this.uniValue.set(null);
          } else {
            // remove deleted item from underlying data and selected options
            this.resource[this.propertyName] = this.data.filter(
              (option) => option._id_ !== id,
            );
            this.selection.set([...this.data]); // spread to trigger change
          }

          // remove from allOptions
          this.allOptions.update((options) => [
            ...options.filter((option) => option._id_ !== id), // spread to trigger change
          ]);
        }
      });
  }
}
