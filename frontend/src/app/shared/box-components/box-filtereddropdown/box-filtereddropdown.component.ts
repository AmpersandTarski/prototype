import { Component, Input } from '@angular/core';
import { ObjectBase } from '../../objectBase.interface';
import { BaseBoxComponent } from '../BaseBoxComponent.class';
import { AmpersandInterfaceComponent } from '../../interfacing/ampersand-interface.class';

@Component({
  selector: 'app-box-filtereddropdown',
  templateUrl: './box-filtereddropdown.component.html',
})
export class BoxFilteredDropdownComponent<
  TItem extends ObjectBase,
  I extends ObjectBase | ObjectBase[],
> extends BaseBoxComponent<TItem, I> {
  @Input() label?: string;

  get resourceData() {
    return this.isRootBox ? this.resource['data'] : this.resource[this.propertyName];
  }

  get propertyData() {
    return this.isRootBox ? this.resource['data'] : this.resource[this.propertyName]?.setRelation;
  }
}
