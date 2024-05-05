import { Component, OnInit } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-markdown',
  templateUrl: './atomic-markdown.component.html',
  styleUrls: ['./atomic-markdown.component.css'],
})
export class AtomicMarkdownComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<string, I>
  implements OnInit
{
  override newValue = '';
}
