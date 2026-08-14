import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NavigationEnd, Router } from '@angular/router';
import { BehaviorSubject, Observable, filter, tap } from 'rxjs';
import { Navbar } from '../interfacing/navbar.interface';
import { Notifications } from '../interfacing/notifications.interface';

export type ImportModeState = {
  importMode: boolean;
  appLocked: boolean;
};

export type ImportCheckResponse = {
  locked: boolean;
  notifications: Notifications;
};

/** The only screen available while the application is locked */
export const IMPORT_SCREEN_ROUTE = '/admin/population/import';

/**
 * Import-bootstrap mode (DesignChoices OK-09).
 *
 * A prototype configured with `global.importMode` boots locked into the import
 * screen; imports commit with deferred invariant checking so data can load
 * across many files. "Start checking" (POST admin/importmode/check) runs the
 * one-time full invariant check: green unlocks the application permanently,
 * red keeps it locked with the violations.
 *
 * The lock itself is enforced server-side (HTTP 423 on everything except the
 * app/admin endpoints); this service mirrors it in the UI. It guards through
 * router events rather than per-route canActivate, so the generated routes
 * (compiler output the framework cannot attach guards to) are covered too.
 */
@Injectable({ providedIn: 'root' })
export class ImportModeService {
  private stateSource = new BehaviorSubject<ImportModeState>({
    importMode: false,
    appLocked: false,
  });

  public state$ = this.stateSource.asObservable();

  constructor(
    private http: HttpClient,
    private router: Router,
  ) {}

  get appLocked(): boolean {
    return this.stateSource.value.appLocked;
  }

  /** Called once at boot (AppComponent): fetch the lock state and guard navigation */
  public init(): void {
    this.http.get<Navbar>('app/navbar').subscribe((navbar) => {
      this.stateSource.next({
        importMode: !!navbar.importMode,
        appLocked: !!navbar.appLocked,
      });
      // The initial navigation may already have completed before this response
      this.redirectWhenLocked(this.router.url);
    });

    this.router.events
      .pipe(filter((event) => event instanceof NavigationEnd))
      .subscribe((event) => {
        this.redirectWhenLocked((event as NavigationEnd).urlAfterRedirects);
      });
  }

  /** The one-time invariant check; green (locked=false) unlocks the application */
  public startChecking(): Observable<ImportCheckResponse> {
    return this.http
      .post<ImportCheckResponse>('admin/importmode/check', {})
      .pipe(
        tap((response) => {
          this.stateSource.next({
            importMode: this.stateSource.value.importMode,
            appLocked: response.locked,
          });
        }),
      );
  }

  private redirectWhenLocked(url: string): void {
    if (this.appLocked && !url.startsWith(IMPORT_SCREEN_ROUTE)) {
      this.router.navigate([IMPORT_SCREEN_ROUTE]);
    }
  }
}
