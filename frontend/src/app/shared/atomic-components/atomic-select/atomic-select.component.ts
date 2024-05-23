import { Component, OnInit } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-select',
  templateUrl: './atomic-select.component.html',
  styleUrls: ['./atomic-select.component.css'],
})
export class AtomicSelectComponent<
  I extends ObjectBase | ObjectBase[],
> extends BaseAtomicComponent<ObjectBase, I> {
  /**
   * Because we don't get correct isUni from template just check if prop is array
   * to see if multiple or single value.
   */
  get propertyIsArray() {
    return Array.isArray(this.resource[this.propertyName]);
  }

  get selectFrom() {
    return this.resource.selectFrom ?? [];
  }
}
