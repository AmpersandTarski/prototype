import { Component, ContentChild, Input, TemplateRef } from '@angular/core';
import { BaseBoxComponent } from '../BaseBoxComponent.class';
import { BoxTableHeaderTemplateDirective } from './box-table-header-template.directive';
import { BoxTableRowTemplateDirective } from './box-table-row-template.directive';

@Component({
  selector: 'app-box-table',
  templateUrl: './box-table.component.html',
  styleUrls: ['./box-table.component.css'],
})
export class BoxTableComponent<TItem extends object, I> extends BaseBoxComponent<I> {
  @Input() data!: TItem[];
  @ContentChild(BoxTableHeaderTemplateDirective, { read: TemplateRef })
  headers?: TemplateRef<unknown>;
  @ContentChild(BoxTableRowTemplateDirective, { read: TemplateRef })
  rows?: TemplateRef<unknown>;
}
