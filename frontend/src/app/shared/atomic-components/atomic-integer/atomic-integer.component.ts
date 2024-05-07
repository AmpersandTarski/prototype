import { Component, OnInit } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-integer',
  templateUrl: './atomic-integer.component.html',
  styleUrls: ['./atomic-integer.component.css'],
})
export class AtomicIntegerComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<number, I>
  implements OnInit {}
