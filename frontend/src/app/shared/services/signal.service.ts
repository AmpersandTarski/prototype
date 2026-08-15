import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { Signal } from '../interfacing/notifications.interface';

/**
 * Service that holds the current signal (process rule) violations for the active roles.
 * The LoggingInterceptor feeds this service whenever an API response contains signals.
 * The topbar uses it to show a badge count; the signals page reads from the backend directly.
 */
@Injectable({
  providedIn: 'root',
})
export class SignalService {
  private signalsSubject = new BehaviorSubject<Signal[]>([]);

  /** Observable of the current signals — subscribe to get live updates */
  public readonly signals$: Observable<Signal[]> =
    this.signalsSubject.asObservable();

  /** Total number of individual violations across all signal rules */
  public get violationCount(): number {
    return this.signalsSubject
      .getValue()
      .reduce((sum, signal) => sum + signal.violations.length, 0);
  }

  constructor(private http: HttpClient) {}

  /**
   * Update the in-memory signals list.
   * Called by LoggingInterceptor whenever an API response contains signal notifications.
   */
  public update(signals: Signal[]): void {
    if (signals && signals.length >= 0) {
      this.signalsSubject.next(signals);
    }
  }

  /**
   * Fetch the current signal violations directly from the backend.
   * The backend reads from the conjunct violation cache (__conj_violation_cache__),
   * filtered to the currently active roles.
   */
  public fetchFromBackend(): Observable<Signal[]> {
    return this.http.get<Signal[]>('admin/signals');
  }
}
