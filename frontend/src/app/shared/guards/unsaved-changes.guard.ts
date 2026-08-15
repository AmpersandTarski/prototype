import { inject } from '@angular/core';
import { CanDeactivateFn } from '@angular/router';
import { TransactionService } from '../services/transaction.service';

/**
 * Blocks navigation away from an interface that has buffered, uncommitted edits
 * (Transactional mode). Confirms with the user; on confirm the buffer is discarded
 * (rollback), on decline the navigation is cancelled.
 */
export const unsavedChangesGuard: CanDeactivateFn<unknown> = () => {
  const txn = inject(TransactionService);
  const active = txn.active;
  if (active && active.isDirty()) {
    const leave = confirm('Lose your edits?');
    if (leave) {
      active.cancel();
    }
    return leave;
  }
  return true;
};
