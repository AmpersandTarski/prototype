import {
  HttpEvent,
  HttpHandler,
  HttpInterceptor,
  HttpRequest,
} from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, ReplaySubject } from 'rxjs';
import { finalize, switchMap, take } from 'rxjs/operators';

/**
 * Serializes the very first backend request of the app.
 *
 * At startup the app fires several API requests in parallel before the
 * browser holds a PHPSESSID cookie. Each of those requests makes the backend
 * create its own PHP session and answer with its own Set-Cookie; whichever
 * response arrives last wins the browser's cookie jar. That is harmless while
 * all sessions are anonymous, but a straggler response can also land AFTER
 * the user logged in (e.g. delayed behind a long login commit) and then
 * silently replaces the logged-in session cookie with a fresh anonymous one —
 * every request from that moment on is answered with 401.
 *
 * This interceptor lets exactly one backend request go first; the rest wait
 * until that response has delivered the session cookie. From then on every
 * request carries the cookie, the backend never creates a second session, and
 * there is no straggler Set-Cookie left to lose the login to.
 */
@Injectable()
export class SessionBootstrapInterceptor implements HttpInterceptor {
  private bootstrapStarted = false;
  private sessionEstablished$ = new ReplaySubject<void>(1);

  intercept(
    req: HttpRequest<unknown>,
    next: HttpHandler,
  ): Observable<HttpEvent<unknown>> {
    // Static assets are served by Apache directly: they don't touch PHP and
    // don't establish a session, so they need not wait and must not be used
    // as the bootstrap request either.
    if (req.url.startsWith('/assets/') || req.url.startsWith('assets/')) {
      return next.handle(req);
    }

    if (!this.bootstrapStarted) {
      this.bootstrapStarted = true;
      return next.handle(req).pipe(
        // finalize covers success, error AND cancellation. On success/error
        // the browser has processed the Set-Cookie by the time the response
        // is delivered (any status: the middleware starts the session before
        // the request is handled). On cancellation of the very first request
        // the queued requests are released and race once among themselves —
        // no worse than the behaviour without this interceptor.
        finalize(() => this.sessionEstablished$.next()),
      );
    }

    return this.sessionEstablished$.pipe(
      take(1),
      switchMap(() => next.handle(req)),
    );
  }
}
