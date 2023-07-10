import { Component, ContentChildren, QueryList } from '@angular/core';
import { ObjectBase } from '../../objectBase.interface';
import { BaseBoxComponent } from '../BaseBoxComponent.class';
import { BoxTabsDirective as BoxTabsDirective } from './box-tabs.directive';

@Component({
  selector: 'app-box-tabs',
  templateUrl: './box-tabs.component.html',
  styleUrls: ['./box-tabs.component.scss'],
})
export class BoxTabsComponent<TItem extends ObjectBase, I> extends BaseBoxComponent<TItem, I> {
  @ContentChildren(BoxTabsDirective) tabs!: QueryList<BoxTabsDirective>;
}
