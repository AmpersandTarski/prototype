import { Directive, Input } from '@angular/core';

/** What the FORM tells the template about each field (see BoxFormComponent). */
export interface BoxFormFields {
  showField(item: object, name: string): boolean;
  fieldClass(item: object, name: string): string;
  count(item: object, name: string): number | null;
}

interface BoxFormTemplateContext<TItem extends object> {
  $implicit: TItem;
  form: BoxFormFields;
}

@Directive({
  // eslint-disable-next-line @angular-eslint/directive-selector
  selector: 'ng-template[boxFormTemplate]',
})
export class BoxFormTemplateDirective<TItem extends object> {
  @Input('boxFormTemplate') data!: TItem[] | '';

  // This context guard provides type hinting when using template directive
  // To understand why/how: https://www.youtube.com/watch?v=dau7kQMdH4A
  static ngTemplateContextGuard<TContextItem extends object>(
    dir: BoxFormTemplateDirective<TContextItem>,
    ctx: unknown,
  ): ctx is BoxFormTemplateContext<TContextItem> {
    return true;
  }
}
