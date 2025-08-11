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
  // @Input() action?: 'toggle' | 'set' | 'clear' = 'toggle';

  onDropdownChange(event: any) {
    // Handle dropdown change event
  }
}
