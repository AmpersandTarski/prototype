import { Component, Input } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-date',
  templateUrl: './atomic-date.component.html',
  styleUrls: ['./atomic-date.component.css'],
})
export class AtomicDateComponent<
  I extends ObjectBase | ObjectBase[],
> extends BaseAtomicComponent<string, I> {
  // Possible formats can be found at https://www.primefaces.org/primeng/calendar.
  // Scroll down to DateFormat for the documentation
  @Input() format = 'yy-mm-dd';

  override newValue = '';
}
