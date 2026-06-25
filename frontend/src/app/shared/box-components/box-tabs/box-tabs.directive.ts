import { Directive, Input, TemplateRef, booleanAttribute } from '@angular/core';

@Directive({
  // eslint-disable-next-line @angular-eslint/directive-selector
  selector: '[tab]',
})
export class BoxTabsDirective {
  @Input() label?: string;
  /** Escaped property name of the sub-interface this tab renders. */
  @Input() subName?: string;
  /** Hide this tab panel when its sub-field holds no records. */
  @Input({ transform: booleanAttribute }) hideSubOnNoRecords = false;
  constructor(public readonly template: TemplateRef<unknown>) {}
}
