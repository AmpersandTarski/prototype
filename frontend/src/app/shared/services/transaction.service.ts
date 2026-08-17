import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';

/**
 * One thing that still has to happen before the open transaction can be completed.
 *
 * It is an invariant violation of the buffered edits, phrased for the user as work to do
 * rather than as a failure. `isNew` marks an entry that the previous dry-run round did not
 * have, i.e. one the user's most recent edit brought about. See DesignChoices OK-11.
 */
export interface WorklistEntry {
  /** The rule's violation message; groups the entries of one rule together. */
  rule: string;
  /** The concrete violation text, or the rule message when the rule gives no tuples. */
  message: string;
  /** True when this entry was absent from the previous round's worklist. */
  isNew: boolean;
}

/**
 * The contract a transactional interface exposes to the global SAVE/CANCEL bar.
 * Implemented by {@link AmpersandInterfaceComponent}. Declared here (not importing
 * the component) to avoid a circular dependency between the service and the component.
 */
export interface TransactionalInterface {
  /** True when there are buffered, uncommitted edits. */
  isDirty(): boolean;
  /** True when the buffered edits satisfy all invariant rules (SAVE enabled). */
  canSave(): boolean;
  /** What still has to happen before this transaction can be completed. */
  worklist(): WorklistEntry[];
  /** Commit the buffered edits as one transaction. */
  save(): Observable<unknown>;
  /** Discard the buffered edits (rollback) and restore the server state. */
  cancel(): void;
}

/**
 * Tracks the interface that currently owns an open (buffered) transaction.
 *
 * The transaction boundary is a single interface (one root interface per route),
 * so a single active interface and a single global SAVE/CANCEL bar suffice.
 */
@Injectable({ providedIn: 'root' })
export class TransactionService {
  private readonly _active$ = new BehaviorSubject<TransactionalInterface | null>(
    null,
  );
  public readonly active$: Observable<TransactionalInterface | null> =
    this._active$.asObservable();

  public get active(): TransactionalInterface | null {
    return this._active$.value;
  }

  /** Register (or clear, with `null`) the interface owning the open transaction. */
  public setActive(ifc: TransactionalInterface | null): void {
    this._active$.next(ifc);
  }

  /** Re-emit the active interface so the bar re-reads isDirty()/canSave(). */
  public refresh(): void {
    this._active$.next(this._active$.value);
  }
}
