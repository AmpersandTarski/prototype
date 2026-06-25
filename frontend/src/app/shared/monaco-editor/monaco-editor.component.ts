import {
  AfterViewInit,
  Component,
  ElementRef,
  EventEmitter,
  Input,
  OnChanges,
  OnDestroy,
  Output,
  SimpleChanges,
  ViewChild,
  inject,
} from '@angular/core';
import { EditorService } from '../services/editor.service';
import { loadMonaco } from './monaco-loader';

/**
 * Thin Angular wrapper around a Monaco code editor. Shows line numbers, binds
 * its content two-way, and registers itself with {@link EditorService} so that
 * clickable diagnostics can move its cursor.
 */
@Component({
  selector: 'app-monaco-editor',
  template: `<div #host class="monaco-host"></div>`,
  styles: [
    `.monaco-host {
      width: 100%;
      height: var(--monaco-height, 360px);
      border: 1px solid var(--surface-300, #dddddd);
    }`,
  ],
})
export class MonacoEditorComponent
  implements AfterViewInit, OnChanges, OnDestroy
{
  @Input() value = '';
  @Output() valueChange = new EventEmitter<string>();
  @Output() blurred = new EventEmitter<void>();
  @Input() language = 'plaintext';
  @Input() readOnly = false;

  @ViewChild('host', { static: true }) host!: ElementRef<HTMLDivElement>;

  private editorSvc = inject(EditorService);
  private editor: any;

  ngAfterViewInit(): void {
    loadMonaco().then((monaco) => {
      this.editor = monaco.editor.create(this.host.nativeElement, {
        value: this.value ?? '',
        language: this.language,
        lineNumbers: 'on',
        automaticLayout: true,
        minimap: { enabled: false },
        scrollBeyondLastLine: false,
        readOnly: this.readOnly,
        tabSize: 2,
      });
      this.editor.onDidChangeModelContent(() => {
        const v = this.editor.getValue();
        this.editorSvc.setEmpty(v.trim().length === 0);
        this.valueChange.emit(v);
      });
      this.editor.onDidBlurEditorWidget(() => this.blurred.emit());
      this.editorSvc.register(this.editor);
      this.editorSvc.setEmpty((this.value ?? '').trim().length === 0);
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (
      this.editor &&
      changes['value'] &&
      this.editor.getValue() !== (this.value ?? '')
    ) {
      this.editor.setValue(this.value ?? '');
    }
  }

  ngOnDestroy(): void {
    if (this.editor) {
      this.editorSvc.unregister(this.editor);
      this.editor.dispose();
    }
  }
}
