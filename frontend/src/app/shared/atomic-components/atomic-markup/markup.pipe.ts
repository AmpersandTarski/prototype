import { Pipe, PipeTransform } from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { MarkupFormat, renderMarkup } from './markup';

/**
 * `text | markup: format` gives the HTML for a text. The pipe is pure, so Angular formats a
 * text again only when the text or the format changes, not on every change detection.
 */
@Pipe({ name: 'markup' })
export class MarkupPipe implements PipeTransform {
  constructor(private sanitizer: DomSanitizer) {}

  transform(text: unknown, format: MarkupFormat): string | SafeHtml {
    const html = renderMarkup(text, format);
    // The sanitiser removes MathML. latex.ts escapes its source and KaTeX runs untrusted, so
    // the LaTeX result needs no sanitising; every other format still goes through it.
    return format === 'LATEX' ? this.sanitizer.bypassSecurityTrustHtml(html) : html;
  }
}
