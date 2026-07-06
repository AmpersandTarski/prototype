/**
 * Registry of fields the user is actively editing (i.e. an input that currently
 * has focus). After every mutation the interface re-syncs with the server and
 * mergeDeep()s the fresh data into the in-memory resource (interim fix for
 * issue #298). Without a guard, that merge overwrites the value in a field the
 * user is at that very moment typing into — its input loses the typed text.
 *
 * This registry lets mergeDeep skip exactly those fields. Focus-based on
 * purpose: a field's OWN post-patch sync must still update it (e.g. a relation
 * cleared by an exec-engine rule), and by the time that patch fires (on blur)
 * the field no longer has focus, so it is not protected. A DIFFERENT field's
 * slow sync landing while you type here finds this field focused and skips it.
 *
 * Keyed by the owning resource's `_path_` plus the property name, so nested and
 * sibling objects are distinguished.
 */
const activeEdits = new Set<string>();

function editKey(path: string, propertyName: string): string {
  return `${path}::${propertyName}`;
}

/** Mark a field as being edited (call on focus). */
export function markEditing(path: string, propertyName: string): void {
  activeEdits.add(editKey(path, propertyName));
}

/** Clear the editing mark for a field (call on blur). */
export function clearEditing(path: string, propertyName: string): void {
  activeEdits.delete(editKey(path, propertyName));
}

/** Is this field currently being edited? Consulted by mergeDeep. */
export function isEditing(path: string, propertyName: string): boolean {
  return activeEdits.has(editKey(path, propertyName));
}
