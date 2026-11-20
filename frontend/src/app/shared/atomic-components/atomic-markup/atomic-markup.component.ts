import { Component, Input, ViewEncapsulation, booleanAttribute } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';
import { MarkupFormat, formatName, markupFormat } from './markup';

/**
 * Shows a text formatted in its markup language (DesignChoices OK-24): Markdown,
 * GitHub-flavoured Markdown, HTML or plain text. Two templates use it:
 * - Box-MARKUP.html, for every item of a `BOX <MARKUP ...>`;
 * - a `Concept-<name>.html` that a project writes, for every text of one concept.
 * With update rights on a univalent field in MARKDOWN or GFM, the field is the WYSIWYG editor
 * `app-markdown-editor` (DesignChoices OK-35); in the other formats, and for a field that holds
 * more texts, it is the text area of BIGALPHANUMERIC. A BOX<MARKUP> passes no update rights, so
 * its texts are for reading.
 */
@Component({
  selector: 'app-atomic-markup',
  templateUrl: './atomic-markup.component.html',
  styleUrls: ['./atomic-markup.component.css'],
  // The HTML comes from [innerHTML], which Angular's emulated encapsulation does not reach;
  // every rule in the stylesheet is therefore scoped under `.markup`.
  encapsulation: ViewEncapsulation.None,
})
export class AtomicMarkupComponent<I extends ObjectBase | ObjectBase[]> extends BaseAtomicComponent<string, I> {
  /** The format named in the template, such as MARKDOWN; used when no format value is given. */
  @Input() format = '';

  /** The value of the format item of the row (dynamic format); overrides `format` when not empty. */
  @Input() formatValue: unknown = null;

  /** The name of the item that carries the format; that item shows nothing. */
  @Input() formatItem = '';

  @Input() label = '';

  @Input({ transform: booleanAttribute }) showLabel = false;

  get isFormatItem(): boolean {
    return this.formatItem !== '' && this.propertyName === this.formatItem;
  }

  get effectiveFormat(): MarkupFormat {
    const dynamic = formatName(this.formatValue);
    return markupFormat(dynamic !== '' ? dynamic : this.format);
  }

  /** Whether the field is the Markdown editor (DesignChoices OK-35). */
  get usesEditor(): boolean {
    const format = this.effectiveFormat;
    return this.canRead() && this.canUpdate() && this.isUni && (format === 'MARKDOWN' || format === 'GFM');
  }

  onSave(text: string): void {
    this.resource[this.propertyName] = text as never;
    this.dirty = true;
    this.updateValue();
  }
}
