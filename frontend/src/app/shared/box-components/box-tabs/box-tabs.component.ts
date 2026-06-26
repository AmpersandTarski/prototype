import { Component, ContentChildren, QueryList } from '@angular/core';
import { ObjectBase } from '../../objectBase.interface';
import { BaseBoxComponent } from '../BaseBoxComponent.class';
import { BoxTabsDirective as BoxTabsDirective } from './box-tabs.directive';

@Component({
  selector: 'app-box-tabs',
  templateUrl: './box-tabs.component.html',
  styleUrls: ['./box-tabs.component.scss'],
})
export class BoxTabsComponent<
  TItem extends ObjectBase,
  I extends ObjectBase | ObjectBase[],
> extends BaseBoxComponent<TItem, I> {
  @ContentChildren(BoxTabsDirective) tabs!: QueryList<BoxTabsDirective>;

  /**
   * Tabs to render for a given record. With `hideSubOnNoRecords` a tab whose
   * sub-field is empty for this record is filtered out. Filtering the list (as
   * opposed to *ngIf on <p-tabPanel>) avoids the PrimeNG tab-index issues that
   * structural directives on tab panels cause.
   */
  public visibleTabs(
    item: ObjectBase & { [key: string]: unknown },
  ): BoxTabsDirective[] {
    return this.tabs.filter((tab) => {
      if (!tab.hideSubOnNoRecords || !tab.subName) return true;
      return this.hasFieldData(item[tab.subName]);
    });
  }
}
