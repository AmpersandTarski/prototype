import { Component } from '@angular/core';
import { Observable } from 'rxjs';
import {
  TransactionService,
  TransactionalInterface,
} from '../../shared/services/transaction.service';

/**
 * SAVE/CANCEL bar for a transactional interface.
 *
 * Mounted by {@link AmpersandInterfaceComponent} inside its own accent-bordered host
 * element, so the controls sit within the border of the (sub-)interface the
 * transaction applies to (not in a global bar at the window bottom). Visible from
 * entry onward. CANCEL is shown only while there are buffered edits to roll back
 * (`isDirty()`); when the interface just shows committed database content there is
 * nothing to cancel. SAVE is disabled while any invariant is violated, and its hover
 * text lists the concrete violations. The transaction boundary is a single interface
 * (one root interface per route), so one bar suffices.
 */
@Component({
  selector: 'app-transaction-bar',
  templateUrl: './transaction-bar.component.html',
  styleUrls: ['./transaction-bar.component.scss'],
})
export class TransactionBarComponent {
  public readonly active$: Observable<TransactionalInterface | null>;

  constructor(private txn: TransactionService) {
    this.active$ = this.txn.active$;
  }

  public save(active: TransactionalInterface): void {
    if (!active.canSave()) return;
    active.save().subscribe();
  }

  public cancel(active: TransactionalInterface): void {
    active.cancel();
  }

  /** Violation lines joined for the disabled-SAVE tooltip (`[escape]="false"`). */
  public violationTooltip(active: TransactionalInterface): string {
    return active.violations().join('<br>');
  }
}
