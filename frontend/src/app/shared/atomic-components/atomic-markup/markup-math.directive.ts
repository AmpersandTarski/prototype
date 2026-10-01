import { AfterViewInit, Directive, ElementRef, Input, OnDestroy, booleanAttribute } from '@angular/core';
import { renderMath } from './latex';

/**
 * Renders the formulas of a LaTeX text (DesignChoices OK-25). The element holds HTML that
 * Angular bound and sanitised; every formula in it is a `markup__math` span with its TeX
 * source as text. This directive lets KaTeX build the MathML of each as DOM nodes, when the
 * element appears and whenever Angular replaces its content.
 */
@Directive({
  // eslint-disable-next-line @angular-eslint/directive-selector
  selector: '[appMarkupMath]',
})
export class MarkupMathDirective implements AfterViewInit, OnDestroy {
  /** Whether the content is LaTeX; other formats have no formulas to render. */
  @Input({ alias: 'appMarkupMath', transform: booleanAttribute }) active = false;

  private observer?: MutationObserver;

  constructor(private readonly host: ElementRef<HTMLElement>) {}

  ngAfterViewInit(): void {
    const element = this.host.nativeElement;
    this.render();
    // [innerHTML] replaces the children of the element; the formulas inside a paragraph are
    // deeper, so rendering them does not wake this observer again.
    this.observer = new MutationObserver(() => this.render());
    this.observer.observe(element, { childList: true });
  }

  ngOnDestroy(): void {
    this.observer?.disconnect();
  }

  private render(): void {
    if (this.active) renderMath(this.host.nativeElement);
  }
}
