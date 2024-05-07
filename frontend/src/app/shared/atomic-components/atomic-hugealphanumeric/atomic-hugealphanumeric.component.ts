import { Component, OnInit } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-hugealphanumeric',
  templateUrl: './atomic-hugealphanumeric.component.html',
  styleUrls: ['./atomic-hugealphanumeric.component.css'],
})
export class AtomicHugealphanumericComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<string, I>
  implements OnInit {}
