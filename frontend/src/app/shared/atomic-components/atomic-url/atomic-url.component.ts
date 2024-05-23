import { Component } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-url',
  templateUrl: './atomic-url.component.html',
  styleUrls: ['./atomic-url.component.css'],
})
export class AtomicUrlComponent<
  I extends ObjectBase | ObjectBase[],
> extends BaseAtomicComponent<string, I> {}
