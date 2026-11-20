import {
  AfterViewInit,
  Component,
  ElementRef,
  EventEmitter,
  Input,
  NgZone,
  OnChanges,
  OnDestroy,
  Output,
  SimpleChanges,
  ViewChild,
  booleanAttribute,
} from '@angular/core';
import type { Editor } from '@milkdown/core';
import { textToStore } from './markdown-merge';
import { safeUrl } from './safe-url';

/**
 * A WYSIWYG editor for a text in MARKDOWN or GFM (DesignChoices OK-35): a person types in the
 * formatted text itself. The editor is Milkdown (ProseMirror with remark), with the presets
 * commonmark and, for GFM, gfm; it renders no formulas.
 *
 * Saving: when the focus leaves the editor and the person changed the document, the editor
 * emits `save` with the text that `textToStore` gives: the stored text with the edited lines
 * replaced, or the editor's Markdown where that merge would change the meaning.
 * A text that is opened and left without a change is not saved.
 *
 * Safety: the editor builds its DOM itself, outside Angular's sanitiser. It creates only the
 * elements of its schema, through ProseMirror; raw HTML in the text is a node that shows its
 * source as text. A link or an image is drawn with the URL that `safeUrl` allows, whether it was
 * loaded, typed or pasted.
 *
 * Milkdown is loaded with a dynamic import, so an application without an editable Markdown
 * text does not load it.
 */
@Component({
  selector: 'app-markdown-editor',
  template: `<div
    #host
    class="markup-editor"
    (focusin)="onFocusIn()"
    (focusout)="onFocusOut($event)"
  ></div>`,
})
export class MarkdownEditorComponent
  implements AfterViewInit, OnChanges, OnDestroy
{
  /** The stored text. */
  @Input() value: string | null = null;

  /** GitHub-flavoured Markdown (tables, strikethrough, task lists) in place of CommonMark. */
  @Input({ transform: booleanAttribute }) gfm = false;

  /** The accessible name of the editor. */
  @Input() label = '';

  /** The new stored text, emitted when the focus leaves the editor after a change. */
  @Output() save = new EventEmitter<string>();

  /** Emitted when the focus enters (true) and leaves (false) the editor. */
  @Output() editing = new EventEmitter<boolean>();

  @ViewChild('host', { static: true }) host!: ElementRef<HTMLDivElement>;

  /** The editor and the Milkdown modules, once both are there. */
  private ready?: { editor: Editor; m: Milkdown };
  private destroyed = false;
  private focused = false;

  /** The text as stored, and the editor's Markdown of the document as it stood then. */
  private stored = '';
  private before = '';

  constructor(private readonly zone: NgZone) {}

  ngAfterViewInit(): void {
    // ProseMirror handles every key and mouse event; outside the zone they cause no change detection.
    this.zone.runOutsideAngular(() => void this.create());
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['value'] && this.ready && !this.focused)
      this.load(this.value ?? '');
  }

  ngOnDestroy(): void {
    this.destroyed = true;
    this.ready?.editor.destroy();
  }

  onFocusIn(): void {
    if (this.focused) return;
    this.focused = true;
    this.editing.emit(true);
  }

  onFocusOut(event: FocusEvent): void {
    // Focus that moves within the editor is no leaving.
    if (
      event.relatedTarget instanceof Node &&
      this.host.nativeElement.contains(event.relatedTarget)
    )
      return;
    this.focused = false;
    this.editing.emit(false);
    this.commit();
  }

  private async create(): Promise<void> {
    const m = await loadMilkdown();
    if (this.destroyed) return;
    const text = this.value ?? '';
    const editor = m.Editor.make()
      .config((ctx) => {
        ctx.set(m.rootCtx, this.host.nativeElement);
        ctx.set(m.defaultValueCtx, text);
        ctx.update(m.editorViewOptionsCtx, (options) => ({
          ...options,
          attributes: {
            class: 'markup',
            'aria-label': this.label,
            'aria-multiline': 'true',
          },
        }));
      })
      .use(m.commonmark)
      .use(m.history)
      .use(m.safeLink)
      .use(m.safeImage);
    if (this.gfm) editor.use(m.gfmPreset);
    const created = await editor.create();
    if (this.destroyed) {
      created.destroy();
      return;
    }
    const ready = { editor: created, m };
    this.ready = ready;
    this.stored = text;
    this.before = markdown(ready);
    // A value that arrived while Milkdown was loading.
    if ((this.value ?? '') !== text) this.load(this.value ?? '');
  }

  /** Shows a stored text that changed outside the editor, such as after a save or a rule. */
  private load(text: string): void {
    const ready = this.ready;
    if (!ready || text === this.stored) return;
    ready.editor.action(ready.m.replaceAll(text, true));
    this.stored = text;
    this.before = markdown(ready);
  }

  private commit(): void {
    const ready = this.ready;
    if (!ready) return;
    const after = markdown(ready);
    const text = textToStore(this.stored, this.before, after, (a, b) =>
      parse(ready, a).eq(parse(ready, b)),
    );
    if (text === null) return;
    this.stored = text;
    this.before = after;
    this.zone.run(() => this.save.emit(text));
  }
}

/** The editor's Markdown of the document it shows. */
function markdown({ editor, m }: { editor: Editor; m: Milkdown }): string {
  return editor.action((ctx) =>
    ctx.get(m.serializerCtx)(ctx.get(m.editorViewCtx).state.doc),
  );
}

/** The document the editor makes of a text. */
function parse({ editor, m }: { editor: Editor; m: Milkdown }, text: string) {
  return editor.action((ctx) => ctx.get(m.parserCtx)(text));
}

type Milkdown = Awaited<ReturnType<typeof importMilkdown>>;

let milkdown: Promise<Milkdown> | undefined;

/** The Milkdown modules, imported once for every editor on the page. */
function loadMilkdown(): Promise<Milkdown> {
  milkdown ??= importMilkdown();
  return milkdown;
}

async function importMilkdown() {
  const [core, commonmarkPreset, gfmPreset, historyPlugin, utils] =
    await Promise.all([
      import('@milkdown/core'),
      import('@milkdown/preset-commonmark'),
      import('@milkdown/preset-gfm'),
      import('@milkdown/plugin-history'),
      import('@milkdown/utils'),
    ]);
  const { linkSchema, imageSchema } = commonmarkPreset;
  return {
    ...core,
    commonmark: commonmarkPreset.commonmark,
    gfmPreset: gfmPreset.gfm,
    history: historyPlugin.history,
    replaceAll: utils.replaceAll,
    // Draw a link and an image with a safe URL; the document keeps the URL as written.
    safeLink: linkSchema.extendSchema((prev) => (ctx) => {
      const schema = prev(ctx);
      return {
        ...schema,
        toDOM: (mark, inline) =>
          schema.toDOM!(
            mark.type.create({
              ...mark.attrs,
              href: safeUrl(mark.attrs['href']),
            }),
            inline,
          ),
      };
    }),
    safeImage: imageSchema.extendSchema((prev) => (ctx) => {
      const schema = prev(ctx);
      return {
        ...schema,
        toDOM: (node) =>
          schema.toDOM!(
            node.type.create({
              ...node.attrs,
              src: safeUrl(node.attrs['src']),
            }),
          ),
      };
    }),
  };
}
