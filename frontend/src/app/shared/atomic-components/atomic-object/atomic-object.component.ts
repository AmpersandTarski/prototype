import { Component, Input, OnInit, Signal, computed } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
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
  public dropdownOptions: ObjectBase[] | undefined;
  public filteredDropdownOptions: ObjectBase[] | undefined;
  public filterValue = '';
  @Input() public placeholder!: string;
  @Input() tgtResourceType!: string;

  override ngOnInit(): void {
    super.ngOnInit();

    if (this.canUpdate()) {
      // Find which entities are able to be added to the dropdown menu
      this.interfaceComponent
        .fetchDropdownMenuData(`resource/${this.tgtResourceType}`)
        .subscribe((objects) => {
          this.dropdownOptions = objects;
          this.filteredDropdownOptions = this.selectableDropdownOptions();
        });
    }
  }

  public handleFilterInput(e: KeyboardEvent): void {
    if (e.code === 'Enter') {
      this.addFilterValue();
      return; // added item, so done!
    }

    if (this.filterValue === '') {
      // reset filter
      this.filteredDropdownOptions = this.selectableDropdownOptions();
    } else {
      // filter according to filterValue, case-insensitive
      this.filteredDropdownOptions = this.selectableDropdownOptions().filter(
        (option) => option._id_.toLowerCase().includes(this.filterValue.toLowerCase()),
      );
    }
  }

  public addFilterValue(): void {
    if (!this.filterValueIsValidToCreate()) {
      return; // value not allowed to create
    }

    const value = this.filterValue;
    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'add',
          path: this.propertyName,
          value: value,
        },
      ])
      .subscribe();
  }

  public filterValueIsValidToCreate(): boolean {
    if (!this.filterValue.trim().length) {
      return false; // empty value not allowed
    }
    // case-insensitive
    return !this.dropdownOptions!.map((option) => option._id_.toLowerCase()).includes(this.filterValue.toLowerCase());
  }

  public override removeItem(index: number) {
    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'remove',
          path: `${this.propertyName}/${this.data[index]._id_}`,
        },
      ])
      .subscribe();
  }

  public deleteItem(index: number) {
    if (!confirm('Delete?')) return;

    this.interfaceComponent
      .delete(
        `${this.resource._path_}/${this.propertyName}/${this.data[index]._id_}`,
      )
      .subscribe();
  }

  /**
   * Exclude current values from dropdown menu
   */
  public selectableDropdownOptions: Signal<ObjectBase[]> =
    computed(() => {
      const ids = this.requireArray(this.resource[this.propertyName]).map(
        (d) => d._id_,
      );
      return this.dropdownOptions?.filter((o) => !ids.includes(o._id_)) ?? [];
    });
}
