import {
  ChangeDetectorRef,
  Component,
  computed,
  Input,
  OnDestroy,
  OnInit,
  Signal,
  signal,
  ViewChild,
} from '@angular/core';
import { map, switchMap, takeUntil, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { Dropdown } from 'primeng/dropdown';
import { isObject } from '../../helper/deepmerge';
import { ObjectBase } from '../../objectBase.interface';
import { MessageService } from 'primeng/api';

enum Mode {
  Default = '',
  BoxFilteredDropdown = 'box-filtereddropdown',
}

@Component({
  selector: 'app-atomic-object',
  templateUrl: './atomic-object.component.html',
  styleUrls: ['./atomic-object.component.scss'],
})
export class AtomicObjectComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<ObjectBase, I>
  implements OnInit, OnDestroy
{
  @Input() public placeholder!: string;
  @Input() public tgtResourceType!: string;

  get selectFrom() {
    return this.resource.selectFrom ?? [];
  }
  // Accept string from templates (e.g. mode="box-filtereddropdown") to avoid AOT enum binding error
  @Input() mode: string = '';
  @Input() strict = false;

  public selectOptions: ObjectBase[] | undefined;

  // stores all options for the dropdown
  public allOptions = signal<ObjectBase[]>([]);

  // in the uni case the input can be the selected object,
  // null when no object is selected, or a string when the user is typing
  public uniValue = signal<ObjectBase | string | null>(null);

  // used for filtering non uni selectable options
  public filterValue = signal<string>('');

  // includes selected id and applies search filter
  public uniSelectableOptions: Signal<ObjectBase[]> = computed(() =>
    this.computeUniSelectableOptions(),
  );

  // excludes selected ids and applies search filter
  public nonUniSelectableOptions: Signal<ObjectBase[]> = computed(() =>
    this.computeNonUniSelectableOptions(),
  );

  // used in the non uni case
  private selection = signal([] as ObjectBase[]);

  // to programmatically control the dropdown
  @ViewChild('dropdown') private dropdown: Dropdown;

  // html helpers
  public isObject = isObject;

  constructor(
    private messageService: MessageService,
    private cd: ChangeDetectorRef
  ) {
    super();
  }

  override ngOnInit(): void {

    // When using this component, it should at least be readable, to be detected through testing the path.
    if (!this.resource || !this.resource._path_) {
      this.messageService.add({
        severity: 'error',
        summary: 'ADL error',
        detail: 'Contents of BOX<> are not readable. Has the BOX<> read property?',
        life: 7000,

      })
      return // nothing more to do but don't let it break the page
      } 
    

    const resourceName = this.resource._path_;

    // In BOX<FILTEREDDROPDOWN> we'll the crud, uni and tot from the relation, not from the box itself
    if (this.mode === 'box-filtereddropdown') {
      // Validate required properties; show error message if either is missing
      if (
        !('setRelation' in this.resource) ||
        !('selectFrom' in this.resource)
      ) {
        this.messageService.add({
          severity: 'error',
          summary: 'ADL error',
          detail: 'BOX-FILTEREDDROPDOWN requires both setRelation and selectFrom properties defined.',
          life: 7000,
        });
        return;
      }

      // Runtime type checks for setRelation and selectFrom
      const relation = this.resource.setRelation;
      const selectFrom = this.resource.selectFrom;

      // Inline function to validate allowed shapes: null, ObjectBase, or ObjectBase[]
      const checkType = (o: any): boolean => {
        const isObjBase = (x: any) =>
          !!x && typeof x === 'object' && '_id_' in x && '_label_' in x;
        return (
          o === null ||
          isObjBase(o) ||
          (Array.isArray(o) && (o.length === 0 || o.every(isObjBase)))
        );
      };

      const relationTypeOk = checkType(relation);
      const selectFromTypeOk = checkType(selectFrom);

      // It seems that if one of those properties isn't a valid type, for example an array of strings, they probably aren't of the same type either.
      // It would be better to do the type check on compiler level
      if (!relationTypeOk || !selectFromTypeOk) {
        this.messageService.add({
          severity: 'error',
          summary: 'ADL error',
          detail: 'BOX-FILTEREDDROPDOWN requires equal types of setRelation and selectFrom.',
          life: 7000,
        });
        return;
      }

      // patching the crud of the relation, replacing the crud of the box itself
      this.crud =
        AtomicObjectComponent.extractCrudFromResourceName(resourceName);

      try {
        // Call parent init. This will generate an error, with a suggestion to sync backend. However, in this case it is likely that the backend is fine, but the CRUD of the relation does not allow reading.
        super.ngOnInit();
      } catch (error) {
        const errorMessage =
          error instanceof Error ? error.message : String(error);
        this.messageService.add({
          severity: 'error',
          summary: 'ADL error',
          detail: errorMessage + ' OR CRUD of relation not readable',
          life: 7000,
        });
        return;
      }

      // Extract isUni and isTot from resource path and override the input values
      const extractedIsUni =
        AtomicObjectComponent.extractIsUniFromResourceName(resourceName);
      const extractedIsTot =
        AtomicObjectComponent.extractIsTotFromResourceName(resourceName);

      if (extractedIsUni !== null) {
        this.isUni = extractedIsUni;
      }
      if (extractedIsTot !== null) {
        this.isTot = extractedIsTot;
      }

      // Now extract the select options from selectFrom
      this.selectOptions = this.selectFrom;

      // override place holders when provided
      if (this.selectOptions !== undefined) {
        if (this.selectOptions.length === 0) {
          this.placeholder =
            this.resource.noOptionsTxt ??
            this.resource.noOptionsTxt ??
            ' - No items to choose from - ';
        } else {
          this.placeholder =
            this.resource.emptyOption ??
            this.resource.emptyOption ??
            ' - Add item - ';
        }
      }
    } else {
      // used as BOX<SOMETHING ELSE> or as atomic-object alone
      super.ngOnInit();
    }

    // is there anything to choose from? Else just a list is displayed
    if (!(this.canUpdate() || this.selectOptions !== undefined)) {
      return;
    }

    // Set up the reactive chain
    const optionsObservable =
      this.selectOptions !== undefined
        ? of(this.selectOptions)
        : this.getBackendDataObservable();

    optionsObservable
      .pipe(
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
            tap((patched) => {}),
            map(() => {
              // For selectOptions case, return updated options
              if (this.selectOptions) {
                const updatedSelectOptionsArray = Array.isArray(
                  this.selectOptions,
                )
                  ? this.selectOptions
                  : [this.selectOptions];
                return [...updatedSelectOptionsArray]; // spread to trigger change
              }
              // For canUpdate case, return current options with spread to trigger change
              return [...this.allOptions()];
            }),
          ),
        ),
        takeUntil(this.destroy$),
      )
      .subscribe((updatedOptions: ObjectBase[]) => {
        this.allOptions.set(updatedOptions);
      });
  }

  override ngOnDestroy(): void {
    // Clean up ViewChild reference to prevent memory leaks
    if (this.dropdown) {
      // Clear the reference to help with garbage collection
      (this.dropdown as any) = null;
    }
    super.ngOnDestroy();
  }

  private computeUniSelectableOptions(): ObjectBase[] {
    const allOptions = this.allOptions();
    const uniValue = this.uniValue();
    if (typeof uniValue !== 'string' || uniValue.trim().length === 0) {
      return allOptions;
    }

    const lowerCaseFilterValue = uniValue.trim().toLowerCase();
    return allOptions.filter((option) =>
      option._label_.toLowerCase().includes(lowerCaseFilterValue),
    );
  }

  private computeNonUniSelectableOptions(): ObjectBase[] {
    // Compute all options, those without exclude selected ids
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
  }

  // used in uni case to get the selected object or null
  public selectableUniValue(): ObjectBase | null {
    const v = this.uniValue();
    // Only an object can be considered selected; strings/null are typing states
    if (!v || typeof v !== 'object') {
      return null;
    }
    const options = this.uniSelectableOptions();
    const found = options.some((o) => o._id_ === (v as ObjectBase)._id_);
    return found ? (v as ObjectBase) : null;
  }

  private getBackendDataObservable() {
    return this.interfaceComponent.fetchDropdownMenuData(
      `resource/${this.tgtResourceType}`,
    );
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
      .pipe(takeUntil(this.destroy$))
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
      .pipe(takeUntil(this.destroy$))
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
      .pipe(takeUntil(this.destroy$))
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
      .pipe(takeUntil(this.destroy$))
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
      .pipe(takeUntil(this.destroy$))
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

  /**
   * Checks if tot constraint allows delete/remove operations
   * @returns true if delete/remove is allowed, false if tot constraint prevents it
   */
  public matchTotConstraint(): boolean {
    if (!this.isTot) {
      return true; // No tot constraint, always allow
    }

    if (this.isUni) {
      // For uni case: don't allow delete if there's currently one selected item
      return !this.resource[this.propertyName];
    } else {
      // For non-uni case: don't allow delete if data length is 1 (would result in 0)
      return this.data.length > 1;
    }
  }

  /**
   * Extracts CRUD values from resource name
   * @param resourceName The resource path containing CRUD patterns
   * @returns Array of CRUD strings found in the resource name
   */
  public static extractCrudFromResourceName(resourceName: string): string {
    // Look for _<crud>_ patterns (4-character CRUD strings surrounded by underscores)
    const crudPattern = /_([CRUDcrud]{4})_/g;
    const matches = Array.from(resourceName.matchAll(crudPattern));
    const result = matches.map((match) => match[1]); // Extract just the CRUD part

    return result[result.length - 1]; // return the last found CRUD
  }

  /**
   * Extracts isUni value from resource name by looking for encoded pattern: _<number>_UNI_<number>_
   * Numbers are generator-encoded ASCII code points (e.g. 40='(', 41=')', 32=' ').
   * The pattern may appear anywhere in the full resource path.
   * @param resourceName The resource path that may contain UNI constraint info
   * @returns true if UNI pattern is found, null if not found
   */
  public static extractIsUniFromResourceName(
    resourceName: string,
  ): boolean | null {
    // Match generic encoded pattern: _<number>_UNI_<number>_
    // Examples: _40_UNI_41_, _40_UNI_32_, etc.
    const uniPattern = /_(\d+)_UNI_(\d+)_/;
    return uniPattern.test(resourceName) ? true : null;
  }

  /**
   * Extracts isTot value from resource name by looking for encoded pattern: _<number>_TOT_<number>_
   * Numbers are generator-encoded ASCII code points (e.g. 40='(', 41=')', 32=' ').
   * The pattern may appear anywhere in the full resource path.
   * @param resourceName The resource path that may contain TOT constraint info
   * @returns true if TOT pattern is found, null if not found
   */
  public static extractIsTotFromResourceName(
    resourceName: string,
  ): boolean | null {
    // Match generic encoded pattern: _<number>_TOT_<number>_
    // Examples: _40_TOT_41_, _32_TOT_41_, etc.
    const totPattern = /_(\d+)_TOT_(\d+)_/;
    return totPattern.test(resourceName) ? true : null;
  }
}
