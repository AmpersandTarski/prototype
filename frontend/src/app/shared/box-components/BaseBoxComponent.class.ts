import { HttpClient } from '@angular/common/http';
import {
  Component,
  ElementRef,
  HostBinding,
  Input,
  OnInit,
  booleanAttribute,
  inject,
} from '@angular/core';
import { FormControl } from '@angular/forms';
import { map, Observable, of } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AmpersandInterfaceComponent } from '../interfacing/ampersand-interface.class';
import { ObjectBase } from '../objectBase.interface';
import { BaseComponent } from '../BaseComponent.class';

@Component({
  template: '',
})
export abstract class BaseBoxComponent<
    TItem extends ObjectBase,
    I extends ObjectBase | ObjectBase[],
  >
  extends BaseComponent
  implements OnInit
{
  @Input() resource!: ObjectBase & { [key: string]: any };
  @Input({ required: true }) propertyName: string;
  @Input({ required: true }) data: TItem[];
  @Input({ required: true }) interfaceComponent: AmpersandInterfaceComponent<I>;
  @Input() crud = 'cRud';
  @Input() placeholder = '';
  @Input({ required: true }) tgtResourceType: string;
  @Input({ transform: booleanAttribute }) isRootBox = false;
  dropdownMenuObjects$: Observable<Array<ObjectBase>> = of();
  newItemControl!: FormControl<string | boolean | number | ObjectBase>;

  @Input({ transform: booleanAttribute }) isUni = false;
  @Input({ transform: booleanAttribute }) isTot = false;

  private hostElementRef = inject(ElementRef) as ElementRef<HTMLElement>;

  /**
   * True when this box is the root of an inlined `INTERFACE <name>` reference
   * to a TRANSACTIONAL interface. The compiler inlines such a reference into
   * the referring interface's template (no component of the referenced
   * interface is instantiated), so this box supplies what the referenced
   * interface would have brought itself: the accent border (host class below)
   * and the SAVE/CANCEL bar, mounted inside this box's host element.
   */
  @HostBinding('class.ampersand-transactional-interface')
  isTransactionalRef = false;

  // BOX-header annotations shared by TABLE, FORM and TABS.
  // The Ampersand compiler passes every BOX-header key/value to the template
  // generically (see renderTemplate in ProtoUtil.hs), so these inputs are
  // populated whenever the scripter writes the matching annotation.
  /** Free-text title/description rendered above the box content. */
  @Input() title?: string;
  /** Hide the whole box (including add-controls) when it has no records. */
  @Input({ transform: booleanAttribute }) hideOnNoRecords = false;
  /** Show a navigation menu (links to other interfaces) per record. */
  @Input({ transform: booleanAttribute }) showNavMenu = false;

  constructor() {
    super();
  }

  ngOnInit(): void {
    if (this.canUpdate()) {
      this.newItemControl = new FormControl<ObjectBase>({} as ObjectBase, {
        nonNullable: true,
        updateOn: 'change',
      });
      this.dropdownMenuObjects$ = this.getDropdownMenuItems(
        this.tgtResourceType,
      );
    }

    if (!this.isRootBox && !(this.propertyName in this.resource)) {
      throw new Error(
        `Property '${this.propertyName}' not defined for object in '${this.resource._path_}'. It is likely that the backend data model is not in sync with the generated frontend.`,
      );
    }

    if (!this.isRootBox) {
      this.isTransactionalRef = this.interfaceComponent.isTransactionalRefBox(
        this.resource?._path_,
        this.propertyName,
      );
      if (this.isTransactionalRef) {
        this.interfaceComponent.mountTransactionBarIn(
          this.hostElementRef.nativeElement,
        );
      }
    }
  }

  public canCreate(): boolean {
    return this.crud[0] == 'C' && (!this.isUni || this.isEmpty());
  }
  public canRead(): boolean {
    return this.crud[1] == 'R';
  }
  public canUpdate(): boolean {
    return this.crud[2] == 'U';
  }
  public canDelete(): boolean {
    return this.crud[3] == 'D';
  }

  public isEmpty(): boolean {
    return this.filterNullish(this.data).length === 0;
  }

  /** True when `hideOnNoRecords` is set and the box has no records. */
  public hideBecauseEmpty(): boolean {
    return this.hideOnNoRecords && this.isEmpty();
  }

  /** True when a sub-field value of an item holds at least one record. */
  public hasFieldData(value: unknown): boolean {
    if (value === null || value === undefined) return false;
    if (Array.isArray(value)) return value.length > 0;
    return true;
  }

  public createItem(): void {
    const path = `${this.resource._path_}/${this.propertyName}`;
    const propertyField = this.isRootBox ? 'data' : this.propertyName;
    this.interfaceComponent
      .post(path)
      .pipe(takeUntil(this.destroy$))
      .subscribe((x) => {
        if (this.isUni) {
          this.resource[propertyField] = x.content as TItem;
        } else {
          if (!this.resource[propertyField]) {
            this.resource[propertyField] = [];
          }
          this.resource[propertyField].unshift(x.content as TItem);
        }
      });
  }

  public addItem() {
    const val = this.newItemControl.value as ObjectBase;

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'add',
          path: this.propertyName,
          value: val._id_,
        },
      ])
      .pipe(takeUntil(this.destroy$))
      .subscribe((x) => {
        if (x.isCommitted && x.invariantRulesHold) {
          // Refresh the dropdown from scratch (like removeItem does) so that
          // stale tap-operators don't accumulate and previously-selected items
          // are correctly restored to the list.
          this.dropdownMenuObjects$ = this.getDropdownMenuItems(
            this.tgtResourceType,
          );
          this.newItemControl.setValue({} as ObjectBase);
        }
      });
  }

  public removeItem(item: TItem): void {
    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'remove',
          path: `${this.propertyName}/${item._id_}`,
        },
      ])
      .pipe(takeUntil(this.destroy$))
      .subscribe((x) => {
        if (x.isCommitted && x.invariantRulesHold) {
          this.dropdownMenuObjects$ = this.getDropdownMenuItems(
            this.tgtResourceType,
          );
        }
      });
  }

  public deleteItem(item: TItem): void {
    if (!confirm('Delete?')) return;

    this.interfaceComponent
      .delete(item._path_)
      .pipe(takeUntil(this.destroy$))
      .subscribe((x) => {
        if (x.isCommitted && x.invariantRulesHold) {
          const index = this.data.indexOf(item);
          if (index != -1) {
            this.data.splice(index, 1);
          }
        }
      });
  }

  private getDropdownMenuItems(
    resourceType: string,
  ): Observable<Array<ObjectBase>> {
    let objects: Observable<Array<ObjectBase>> =
      this.interfaceComponent.fetchDropdownMenuData(`resource/${resourceType}`);
    objects = objects.pipe(
      map((dropdownobjects) =>
        dropdownobjects.filter(
          (object) =>
            !this.filterNullish(this.data)
              .map((y) => y._id_)
              .includes(object._id_),
        ),
      ),
    );
    return objects;
  }

  public filterNullish<T>(data: T[]): T[] {
    return data.filter((item) => item !== null && item !== undefined);
  }
}
