import { Component } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-boolean',
  templateUrl: './atomic-boolean.component.html',
  styleUrls: ['./atomic-boolean.component.css'],
})
export class AtomicBooleanComponent<I extends ObjectBase | ObjectBase[]> extends BaseAtomicComponent<boolean, I> {
  override updateValue() {
    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'replace',
          path: this.propertyName,
          value: this.resource[this.propertyName],
        },
      ])
      .subscribe();
  }
}
