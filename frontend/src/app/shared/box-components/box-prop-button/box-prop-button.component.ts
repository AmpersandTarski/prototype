import { Component, Input } from '@angular/core';
import { takeUntil } from 'rxjs/operators';
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

    // A PROPBUTTON is an explicit action: in Transactional mode it flushes the
    // buffer (acts as SAVE), in Direct mode it patches immediately.
    this.interfaceComponent
      .commitAction(item._path_, [
        { op: 'replace', path: 'property', value: value },
      ])
      .pipe(takeUntil(this.destroy$))
      .subscribe((x: any) => {
        if (x && x.isCommitted) {
          this.data = [x.content as any as TItem];
        }
      });
  }
}
