import { Component } from '@angular/core';
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
export class BoxPropButtonComponent<TItem extends PropButtonItem, I> extends BaseBoxComponent<TItem, I> {
  toggleProperty(item: TItem) {
    this.interfaceComponent
      .patch<TItem>(item._path_, [{ op: 'replace', path: 'property', value: !item.property }])
      .subscribe((x) => {
        if (x.isCommitted) {
          this.data = [x.content];
        }
      });
  }
}
