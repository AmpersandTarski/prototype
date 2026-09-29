import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ObjectBase } from '../../objectBase.interface';

/**
 * The heading above an interface. The interface's label is projected as
 * content. When the interface shows one item (an interface on I[Concept]), the
 * item itself becomes the title and the interface label a small line above it,
 * so the page names what the user looks at: "R01 Leerbaarheid" rather than
 * "Eis". A session interface, or one that shows a list, keeps its label as
 * the heading.
 */
@Component({
  selector: 'app-interface-heading',
  standalone: true,
  imports: [CommonModule],
  template: `
    <h3
      class="interface-heading__name"
      [class.interface-heading__name--eyebrow]="itemTitle"
    >
      <ng-content></ng-content>
    </h3>
    <h2 *ngIf="itemTitle as title" class="interface-heading__title">
      {{ title }}
    </h2>
  `,
  styles: [
    `
      :host {
        display: block;
        margin-bottom: 1rem;
      }
      .interface-heading__name--eyebrow {
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--text-color-secondary);
        margin: 0 0 0.25rem;
      }
      .interface-heading__title {
        font-size: 1.6rem;
        line-height: 1.25;
        margin: 0;
        max-width: 60ch;
        text-wrap: balance;
      }
    `,
  ],
})
export class InterfaceHeadingComponent {
  @Input() resource?: ObjectBase & { data?: any };

  get itemTitle(): string | null {
    const data = this.resource?.data;
    if (data == null || Array.isArray(data) || typeof data !== 'object') {
      return null;
    }
    const path = String(data._path_ ?? this.resource?._path_ ?? '');
    if (path.startsWith('resource/SESSION/')) return null;
    const label = data._label_;
    return label ? String(label) : null;
  }
}
