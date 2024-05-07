import { Component, Input } from '@angular/core';
import { BaseBoxComponent } from '../BaseBoxComponent.class';
import { ObjectBase } from '../../objectBase.interface';
type PropButtonItem = ObjectBase & {
  label: string;
  property: boolean;
};
@Component({
  selector: 'app-box-prop-button',
  templateUrl: './box-prop-button.component.html',
  styleUrls: ['./box-prop-button.component.scss'],
})
export class BoxPropButtonComponent<
  TItem extends PropButtonItem,
  I extends ObjectBase | ObjectBase[],
> extends BaseBoxComponent<TItem, I> {
  @Input() action?: 'toggle' | 'set' | 'clear' = 'toggle';

  handleClick(item: TItem) {
    let value: boolean;

    switch (this.action) {
      case 'set':
        value = true;
        break;
      case 'clear':
        value = false;
        break;
      case 'toggle':
      default:
        value = !item.property;
        break;
    }

    this.interfaceComponent.patch(item._path_, [{ op: 'replace', path: 'property', value: value }]).subscribe((x) => {
      if (x.isCommitted) {
        this.data = [x.content as any as TItem];
      }
    });
  }
}
