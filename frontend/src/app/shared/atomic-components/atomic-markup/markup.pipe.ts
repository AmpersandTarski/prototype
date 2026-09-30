import { Pipe, PipeTransform } from '@angular/core';
import { MarkupFormat, renderMarkup } from './markup';

/**
 * `text | markup: format` gives the HTML for a text. The pipe is pure, so Angular formats a
 * text again only when the text or the format changes, not on every change detection.
 */
@Pipe({ name: 'markup' })
export class MarkupPipe implements PipeTransform {
  transform(text: unknown, format: MarkupFormat): string {
    return renderMarkup(text, format);
  }
}
