import { Component, Input, OnInit } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-datetime',
  templateUrl: './atomic-datetime.component.html',
  styleUrls: ['./atomic-datetime.component.css'],
})
export class AtomicDatetimeComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<string, I>
  implements OnInit
{
  // Possible formats can be found at https://www.primefaces.org/primeng/calendar.
  // Scroll down to DateFormat for the documentation
  @Input() format: string = 'yy-mm-dd';

  override newValue = '';
}
