import { Injectable } from '@angular/core';

/**
 * Tracks the currently mounted code editor so that other parts of the UI
 * (e.g. clickable compiler diagnostics) can move its cursor to a position.
 *
 * One editor is active per page (a single script editor), so a single
 * reference suffices. The editor instance is the Monaco IStandaloneCodeEditor;
 * it is kept as `unknown` here to avoid a hard dependency on the Monaco types.
 */
@Injectable({ providedIn: 'root' })
export class EditorService {
  private editor: any | null = null;
  private empty = true;

  public register(editor: any): void {
    this.editor = editor;
  }

  public unregister(editor: any): void {
    if (this.editor === editor) {
      this.editor = null;
      this.empty = true;
    }
  }

  public hasEditor(): boolean {
    return this.editor !== null;
  }

  /** The editor reports whether its content is empty (used to gate actions). */
  public setEmpty(empty: boolean): void {
    this.empty = empty;
  }

  public isEmpty(): boolean {
    return this.empty;
  }

  /** Move the cursor to (line, column) and reveal it. 1-based. */
  public reveal(line: number, column = 1): boolean {
    if (!this.editor) return false;
    const position = { lineNumber: line, column };
    this.editor.revealPositionInCenter(position);
    this.editor.setPosition(position);
    this.editor.focus();
    return true;
  }
}
