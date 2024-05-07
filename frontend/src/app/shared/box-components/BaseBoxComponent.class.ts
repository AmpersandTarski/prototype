import { HttpClient } from '@angular/common/http';
import { Component, Input, OnInit, booleanAttribute } from '@angular/core';
import { FormControl } from '@angular/forms';
import { map, Observable, of, tap } from 'rxjs';
import { AmpersandInterfaceComponent } from '../interfacing/ampersand-interface.class';
import { ObjectBase } from '../objectBase.interface';

@Component({
  template: '',
})
export abstract class BaseBoxComponent<TItem extends ObjectBase, I extends ObjectBase | ObjectBase[]>
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

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    if (this.canUpdate()) {
      this.newItemControl = new FormControl<ObjectBase>({} as ObjectBase, {
        nonNullable: true,
        updateOn: 'change',
      });
      this.dropdownMenuObjects$ = this.getDropdownMenuItems(this.tgtResourceType);
    }
  }

  public canCreate(): boolean {
    return this.crud[0] == 'C';
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

  public createItem(): void {
    const path = `${this.resource._path_}/${this.propertyName}`;
    const propertyField = this.isRootBox ? 'data' : this.propertyName;
    this.interfaceComponent.post(path).subscribe((x) => {
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
    const propertyField = this.isRootBox ? 'data' : this.propertyName;

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'add',
          path: this.propertyName,
          value: val._id_,
        },
      ])
      .subscribe((x) => {
        if (x.isCommitted && x.invariantRulesHold) {
          // TODO: fix ugly any type
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          this.resource[propertyField] = (x.content as any)[this.propertyName] as TItem[];

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

  public removeItem(index: number): void {
    if (!confirm('Remove?')) return;
    const propertyField = this.isRootBox ? 'data' : this.propertyName;

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'remove',
          path: `${this.propertyName}/${this.data[index]._id_}`,
        },
      ])
      .subscribe((x) => {
        if (x.isCommitted && x.invariantRulesHold) {
          // TODO: fix ugly any type
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          this.resource[propertyField] = (x.content as any)[this.propertyName] as TItem[];

          this.dropdownMenuObjects$ = this.getDropdownMenuItems(this.tgtResourceType);
        }
      });
  }

  public deleteItem(index: number): void {
    if (!confirm('Delete?')) return;

    this.interfaceComponent.delete(this.data[index]._path_).subscribe((x) => {
      if (x.isCommitted && x.invariantRulesHold) {
        this.data.splice(index, 1);
      }
    });
  }

  private getDropdownMenuItems(resourceType: string): Observable<Array<ObjectBase>> {
    let objects: Observable<Array<ObjectBase>> = this.interfaceComponent.fetchDropdownMenuData(
      `resource/${resourceType}`,
    );
    objects = objects.pipe(
      map((dropdownobjects) => dropdownobjects.filter((object) => !this.data.map((y) => y._id_).includes(object._id_))),
    );
    return objects;
  }

  public filterNullish<T>(data: T[]): T[] {
    return data.filter((item) => item !== null && item !== undefined);
  }
}
