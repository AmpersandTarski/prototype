import { Component, OnInit, OnDestroy } from '@angular/core';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { SignalService } from 'src/app/shared/services/signal.service';
import { Signal } from 'src/app/shared/interfacing/notifications.interface';

@Component({
  selector: 'app-signals',
  templateUrl: './signals.component.html',
})
export class SignalsComponent implements OnInit, OnDestroy {
  signals: Signal[] = [];
  loading = false;
  loadError: string | null = null;

  private destroy$ = new Subject<void>();

  constructor(private signalService: SignalService) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    this.loadError = null;
    this.signalService
      .fetchFromBackend()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (signals) => {
          this.signals = signals;
          // Also update the service so the topbar badge stays in sync
          this.signalService.update(signals);
          this.loading = false;
        },
        error: (err) => {
          this.loadError = err?.message ?? 'Laden van signalen mislukt.';
          this.loading = false;
        },
      });
  }

  /** Total number of individual violations */
  get totalViolations(): number {
    return this.signals.reduce((sum, s) => sum + s.violations.length, 0);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
