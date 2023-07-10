import { Directive, Input, TemplateRef } from '@angular/core';

@Directive({
  // eslint-disable-next-line @angular-eslint/directive-selector
  selector: '[tab]',
})
export class BoxTabsDirective {
  @Input() label?: string;
  constructor(public readonly template: TemplateRef<unknown>) {}
}
