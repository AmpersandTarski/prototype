import { Component, ContentChildren, QueryList, inject } from '@angular/core';
import { ObjectBase } from '../../objectBase.interface';
import { InterfacesJsonService } from '../../services/interfaces-json.service';
import { BaseBoxComponent } from '../BaseBoxComponent.class';
import { RecordFields } from '../record-fields';
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

  /** Which fields of a record are empty, in the sense a FORM uses. */
  private fields = new RecordFields(inject(InterfacesJsonService));

  /**
   * Tabs to render for a given record. With `hideSubOnNoRecords` a tab whose
   * sub-field is empty for this record is filtered out, empty as a FORM means
   * it: a tab that holds a group (a box on I) is empty when none of the group's
   * fields has a value or may be filled. A tab whose own box says
   * `showOnNoRecords` stays. Filtering the list (as opposed to *ngIf on
   * <p-tabPanel>) avoids the PrimeNG tab-index issues that structural
   * directives on tab panels cause.
   */
  public visibleTabs(
    item: ObjectBase & { [key: string]: unknown },
  ): BoxTabsDirective[] {
    return this.tabs.filter((tab) => {
      if (!tab.hideSubOnNoRecords || !tab.subName) return true;
      const meta = this.fields.metaOf(item, tab.subName);
      if (!this.fields.isEmptyValue(item[tab.subName], meta)) return true;
      return meta?.boxAnnotations.includes('showOnNoRecords') ?? false;
    });
  }
}
