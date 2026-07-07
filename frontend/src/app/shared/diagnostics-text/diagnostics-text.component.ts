import { Component, Input, OnChanges, inject } from '@angular/core';
import { EditorService } from '../services/editor.service';

interface Part {
  text: string;
  line?: number;
  column?: number;
}

/**
 * Renders read-only text (e.g. a compiler message) and turns every
 * `file:line[:column]` position into a clickable link that moves the active
 * code editor's cursor there (via {@link EditorService}). Whitespace and line
 * breaks are preserved, so multi-line messages stay readable.
 */
@Component({
  selector: 'app-diagnostics-text',
  template: `<span class="diagnostics-text"
    ><ng-container *ngFor="let part of parts"
      ><a
        *ngIf="part.line; else plain"
        class="diagnostic-link"
        (click)="jump(part)"
        >{{ part.text }}</a
      ><ng-template #plain>{{ part.text }}</ng-template></ng-container
    ></span
  >`,
  styles: [
    `.diagnostics-text {
      white-space: pre-wrap;
    }
    .diagnostic-link {
      color: var(--primary-color, #3b82f6);
      cursor: pointer;
      text-decoration: underline;
    }`,
  ],
})
export class DiagnosticsTextComponent implements OnChanges {
  @Input() text = '';
  public parts: Part[] = [];

  private editorSvc = inject(EditorService);

  // file (ending in a known Ampersand source extension) : line [ : column ]
  private static readonly POSITION =
    /([^\s:]+\.(?:adl|ifc|docadl)):(\d+)(?::(\d+))?/g;

  ngOnChanges(): void {
    this.parts = this.parse(this.text ?? '');
  }

  jump(part: Part): void {
    if (part.line) {
      this.editorSvc.reveal(part.line, part.column ?? 1);
    }
  }

  private parse(text: string): Part[] {
    const parts: Part[] = [];
    let last = 0;
    const re = DiagnosticsTextComponent.POSITION;
    re.lastIndex = 0;
    let m: RegExpExecArray | null;
    while ((m = re.exec(text)) !== null) {
      if (m.index > last) {
        parts.push({ text: text.slice(last, m.index) });
      }
      parts.push({
        text: m[0],
        line: Number(m[2]),
        column: m[3] ? Number(m[3]) : 1,
      });
      last = m.index + m[0].length;
    }
    if (last < text.length) {
      parts.push({ text: text.slice(last) });
    }
    return parts;
  }
}
