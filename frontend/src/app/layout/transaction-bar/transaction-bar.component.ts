import { Component } from '@angular/core';
import { Observable } from 'rxjs';
import {
  TransactionService,
  TransactionalInterface,
} from '../../shared/services/transaction.service';

/**
 * Global SAVE/CANCEL bar for a transactional interface.
 *
 * Shown only while an interface has buffered, uncommitted edits. The transaction
 * boundary is a single interface (one root interface per route), so one bar suffices.
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
    active.save().subscribe();
  }

  public cancel(active: TransactionalInterface): void {
    active.cancel();
  }
}

