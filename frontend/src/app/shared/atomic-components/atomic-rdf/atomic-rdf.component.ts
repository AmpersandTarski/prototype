import { Component, OnInit } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-rdf',
  templateUrl: './atomic-rdf.component.html',
  styleUrls: ['./atomic-rdf.component.scss'],
})
export class AtomicRdfComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<string, I>
  implements OnInit
{
  override newValue = '';
}
