import { HttpClient } from '@angular/common/http';
import { Component, Input, OnInit, booleanAttribute } from '@angular/core';
import { FormControl } from '@angular/forms';
import { map, Observable, of, tap } from 'rxjs';
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
> extends BaseComponent implements OnInit
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

  public createItem(): void {
    const path: string = `${this.resource._path_}/${this.propertyName}`;
    const propertyField = this.isRootBox ? 'data' : this.propertyName;
    this.interfaceComponent.post(path).pipe(takeUntil(this.destroy$)).subscribe((x) => {
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
          // remove the recently added item from the dropdown menu
          this.dropdownMenuObjects$ = this.dropdownMenuObjects$.pipe(
            tap((objects) =>
              objects.forEach((item, index) => {
                if (item._id_ === val._id_) {
                  objects.splice(index, 1);
                }
              }),
            ),
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

    this.interfaceComponent.delete(item._path_).pipe(takeUntil(this.destroy$)).subscribe((x) => {
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
          (object) => !this.data.map((y) => y._id_).includes(object._id_),
        ),
      ),
    );
    return objects;
  }

  public filterNullish<T>(data: T[]): T[] {
    return data.filter((item) => item !== null && item !== undefined);
  }
}
