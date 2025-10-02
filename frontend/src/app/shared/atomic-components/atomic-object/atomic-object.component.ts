import {
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
import {
  InterfacesJsonService,
  SubObjectMeta,
} from '../../services/interfaces-json.service';

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

  public conceptType: string = 'item';

  get selectFrom() {
    return this.resource.selectFrom ?? [];
  }
  // Accept string from templates (e.g. mode="box-filtereddropdown") to avoid AOT enum binding error
  @Input() mode: string = '';
  @Input() strict = false;

  public selectOptions: ObjectBase[] | undefined;

  // stores all options for the dropdown
  public allOptions = signal<ObjectBase[]>([]);

  // dynamic placeholder that reacts to resource changes
  public placeHolder$: Signal<string> = computed(() =>
    this.computePlaceholder(),
  );

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

  constructor(private interfacesLoader: InterfacesJsonService, private messageService: MessageService) {
    super();
  }

  override async ngOnInit(): Promise<void> {

    // In BOX<FILTEREDDROPDOWN> we'll use the crud, uni and tot from setRelation, not from the box itself
    if (this.mode === 'box-filtereddropdown') {

    // When using this component, it should at least be readable, to be detected through testing the path.
    if (!this.resource || !this.resource._path_) {

      this.messageService.add({
        severity: 'error',
        summary: 'ADL error',
        detail: 'Contents of BOX<FILTEREDDROPDOWN> are not readable. Has the BOX read property?',
        life: 7000,

      })
      return // nothing more to do but don't let it break the page
      }

      // Validate required properties; show error message if either is missing
      if (
        !('setRelation' in this.resource) ||
        !('selectFrom' in this.resource)
      ) {
        this.messageService.add({
          severity: 'error',
          summary: 'ADL error',
          detail: 'BOX<FILTEREDDROPDOWN> requires both setRelation and selectFrom properties defined.',
          life: 7000,
        });
        return;
      }

      // Load and find subobjects information from interfaces.json
      let relation: SubObjectMeta | null = null;
      let selectFrom: SubObjectMeta | null = null;

      try {
        selectFrom = await this.interfacesLoader.findSubObject(this.resource._path_, 'selectFrom');
        relation = await this.interfacesLoader.findSubObject(this.resource._path_, 'setRelation');

      } catch (error) {
        console.error('Error finding setRelation or selectFrom in BOX-FILTEREDDROPDOWN:', error);
      }

      this.crud = relation?.crud ?? 'cRud';
      this.isUni = relation?.isUni ?? false;
      this.isTot = relation?.isTot ?? false;

      // Set conceptType from relation
      this.conceptType = relation?.conceptType ?? 'item';

      // Runtime type checks for setRelation and selectFrom from resource properties
      if (relation?.conceptType !== selectFrom?.conceptType) {
        this.messageService.add({
          severity: 'error',
          summary: 'ADL error',
          detail: 'BOX-FILTEREDDROPDOWN requires equal types of setRelation and selectFrom.',
          life: 7000,
        });
      }

      super.ngOnInit();

      // Now extract the select options from selectFrom
      this.selectOptions = this.selectFrom;

    } else {
      // used as BOX<SOMETHING ELSE> or as atomic-object alone

      super.ngOnInit();
    }

    // is there anything to choose from? Else just a list is displayed
    if (!(this.canUpdate() || this.selectOptions !== undefined)) {
      return;
    }

    // Set up the reactive chain, selectOptions is already provided when in BOX<FILTEREDDROPDOWN> mode
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

        // Update uniValue signal when resource changes to trigger dynamicPlaceholder
        if (this.isUni) {
          this.uniValue.set(this.resource[this.propertyName] ?? null);
        } else {
          this.selection.set([...this.data]); // spread to trigger change
        }
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

    // In box-filtereddropdown mode, don't automatically remove selected items
    // Let the user control filtering through ADL expressions like eligible-projectMember
    let optionsToFilter: ObjectBase[];
    if (this.mode === Mode.BoxFilteredDropdown) {
      optionsToFilter = this.allOptions();
    } else {
      // For regular mode, exclude selected ids (original behavior)
      const selectedIds = this.selection().map((d: ObjectBase) => d._id_);
      optionsToFilter = this.allOptions().filter(
        (option) => !selectedIds.includes(option._id_),
      );
    }

    // check if a search filter is applied
    const lowerCaseFilterValue = this.filterValue().trim().toLowerCase();
    const filterIsApplied = lowerCaseFilterValue.length !== 0;
    if (!filterIsApplied) {
      return optionsToFilter;
    }

    // filter options by search term
    return optionsToFilter.filter((option) =>
      option._label_.toLowerCase().includes(lowerCaseFilterValue),
    );
  }

  private computePlaceholder(): string {
    if (this.mode !== 'box-filtereddropdown' || this.selectOptions === undefined) {
      return this.placeholder; // use input placeholder for non-box mode
    }

    const conceptType = this.conceptType.toLowerCase();

    if (this.selectOptions.length === 0) {
      return this.resource.noOptionsTxt ??
             ` - No ${conceptType} to choose from - `;
    }

    // For UNI + canUpdate case, show existing value if present instead of "- Add item -"
    // Use uniValue signal to trigger reactivity when resource changes
    const currentUniValue = this.uniValue();
    if (this.isUni && this.canUpdate() && currentUniValue && typeof currentUniValue === 'object' && currentUniValue._label_) {

      // if a value is selected, show the current value as placeholder
      return currentUniValue._label_;
    } else {
      return this.resource.emptyOption ??
             ` - Add ${conceptType} - `;
    }
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

  // fetch options from backend
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
   * Returns appropriate text for uni relationships when no object is selected
   * @returns emptyOption text if set, otherwise "No <object type> selected"
   */
  public getUniEmptyText(): string {
    if (this.resource.emptyOption) {
      return this.resource.emptyOption;
    }
    return `No ${this.conceptType.toLowerCase()} selected`;
  }

  /**
   * Checks if tot constraint allows delete/remove operations
   * @returns true if delete/remove is allowed, false if tot constraint prevents it
   */
  public matchTotConstraint(): boolean {
    if (!this.isTot) {
      return true; // No tot constraint, always allow delete or remove
    }

    if (this.isUni) {

      // In the Uni Tot case there is always one selected item
      // and that item cannot be deleted nor removed. It can only be replaced through update or create.
      // so this statement should actually always be false:
      return !this.resource[this.propertyName];
    } else {
      // For non-uni case: don't allow delete if data length is 1 (would result in 0)
      return this.data.length > 1;
    }
  }

}
