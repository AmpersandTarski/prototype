import { Injectable, OnDestroy } from '@angular/core';
import { Subject } from 'rxjs';

/**
 * Base component that provides automatic subscription cleanup via destroy$ pattern.
 * All components should extend this to prevent memory leaks.
 */
@Injectable()
export abstract class BaseComponent implements OnDestroy {
  protected destroy$ = new Subject<void>();

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
