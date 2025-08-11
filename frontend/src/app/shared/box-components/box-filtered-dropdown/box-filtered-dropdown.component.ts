import { Component, Input } from '@angular/core';
import { ObjectBase } from '../../objectBase.interface';
import { BaseBoxComponent } from '../BaseBoxComponent.class';


@Component({
  selector: 'app-box-filtered-dropdown',
  templateUrl: './box-filtered-dropdown.component.html',
  styleUrls: ['./box-filtered-dropdown.component.scss'],
})
export class BoxFilteredDropdownComponent<
  TItem extends ObjectBase,
  I extends ObjectBase | ObjectBase[],
> extends BaseBoxComponent<TItem, I> {
  
  /**
   * Because we don't get correct isUni from template just check if prop is array
   * to see if multiple or single value.
   */
  get propertyIsArray() {
    return Array.isArray(this.resource[this.propertyName]);
  }

  get selectFrom() {
    return this.resource['selectFrom'] ?? [];
  }

  dirty = false;

  onDropdownChange(event: any) {
    // Handle dropdown change event - mark as dirty and update value
    this.dirty = true;
    // The value update is handled by ngModel binding
  }

  updateValue() {
    if (!this.dirty) return;

    // transform empty string to null value
    if (this.resource[this.propertyName] === '') {
      this.resource[this.propertyName] = null;
    }

    const val = this.resource[this.propertyName];

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'replace',
          path: this.propertyName,
          // Send _id_ of object when present, primitive value otherwise
          value: val?._id_ ? val._id_ : val,
        },
      ])
      .subscribe(() => {
        this.dirty = false;
      });
  }

  addValue() {
    const val = this.newItemControl.value as any;
    if (val && val._id_) {
      this.addItem();
    }
  }

  removeItemByIndex(index: number) {
    if (this.data && this.data[index]) {
      this.removeItem(this.data[index]);
    }
  }
}
