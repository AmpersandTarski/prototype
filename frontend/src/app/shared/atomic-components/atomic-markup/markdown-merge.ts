import { diff3Merge, diffComm } from 'node-diff3';

/**
 * Puts the edit a person made in a Markdown editor into the stored text, and leaves every line
 * the person did not touch as it was stored (DesignChoices OK-35).
 *
 * A WYSIWYG editor reads a text into a document model and writes new Markdown from that model.
 * That new Markdown normalises what the person never typed: `\.` loses its backslash, `-` in a
 * list becomes `*`, a table separator `---` becomes `-`. Three texts are therefore at hand:
 * - `stored`, the text as it is in the database;
 * - `before`, the editor's Markdown of that text, before the person typed;
 * - `after`, the editor's Markdown when the person leaves the field.
 * `before` → `stored` is the normalisation, `before` → `after` is the edit. A three-way merge
 * with `before` as the base keeps the stored line wherever only the normalisation differs, and
 * takes the edited line wherever the person typed.
 *
 * Where the edit and the normalisation touch neighbouring lines, a three-way merge sees one
 * conflict. When the stored and the normalised lines of that conflict pair up one to one, as
 * they do for escapes and list markers, each untouched line keeps its stored spelling and only
 * the edited lines take the editor's; otherwise the whole conflict takes the edit.
 *
 * The merge works per line. A text keeps its line ends (Windows or Unix) and whether it ends
 * with a line end; Markdown gives neither a meaning.
 */
export function mergeEdit(
  stored: string,
  before: string,
  after: string,
): string {
  if (after === before) return stored;
  const eol = stored.includes('\r\n') ? '\r\n' : '\n';
  const lines = (text: string) => text.replace(/\r?\n$/, '').split(/\r?\n/);
  const merged: string[] = [];
  for (const chunk of diff3Merge(lines(stored), lines(before), lines(after))) {
    if (chunk.ok) merged.push(...chunk.ok);
    else if (chunk.conflict)
      merged.push(
        ...resolve(chunk.conflict.a, chunk.conflict.o, chunk.conflict.b),
      );
  }
  const text = merged.join(eol);
  if (text === '' || !/\r?\n$/.test(stored)) return text;
  return text + eol;
}

/**
 * The text to store when the person leaves the editor, or `null` when the document did not
 * change and nothing is to be stored. It is the merge of `mergeEdit`, as long as that merge
 * means what the editor shows; `sameMeaning` compares two texts as the editor parses them. When
 * the merge means something else, the editor's own Markdown is stored, in its own spelling.
 */
export function textToStore(
  stored: string,
  before: string,
  after: string,
  sameMeaning: (a: string, b: string) => boolean,
): string | null {
  if (after === before) return null;
  const merged = mergeEdit(stored, before, after);
  return sameMeaning(merged, after) ? merged : after;
}

/** A conflict: `stored` and `edited` both differ from `base`. */
function resolve(stored: string[], base: string[], edited: string[]): string[] {
  if (stored.length !== base.length) return edited;
  const out: string[] = [];
  let i = 0;
  for (const part of diffComm(base, edited)) {
    if (part.common) {
      // A common line stands for the stored line at the same place, in its stored spelling.
      out.push(...stored.slice(i, i + part.common.length));
      i += part.common.length;
    } else {
      out.push(...part.buffer2);
      i += part.buffer1.length;
    }
  }
  return out;
}
