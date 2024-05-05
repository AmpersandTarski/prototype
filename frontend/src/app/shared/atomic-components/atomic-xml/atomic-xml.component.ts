import { Component, OnInit } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-xml',
  templateUrl: './atomic-xml.component.html',
  styleUrls: ['./atomic-xml.component.css'],
})
export class AtomicXmlComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<string, I>
  implements OnInit
{
  override newValue = '';
}
