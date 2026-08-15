import { Injectable } from '@angular/core';
import {
  HttpRequest,
  HttpHandler,
  HttpEvent,
  HttpInterceptor,
  HttpErrorResponse,
} from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { Router } from '@angular/router';
import { catchError } from 'rxjs';

@Injectable()
export class HttpErrorInterceptor implements HttpInterceptor {
  constructor(private router: Router) {}

  /**
   * HttpErrorResponses are intercepted here.
   *
   * The interceptor only performs side-effects that depend on the status
   * (e.g. navigating on a 404). It then re-throws the ORIGINAL error so that
   * consumers using `firstValueFrom`/`first()` reject with the real
   * `HttpErrorResponse` instead of an empty stream.
   *
   * Presenting the error to the user is the sole responsibility of
   * `GlobalErrorHandler`, so there is exactly one toast per failure.
   *
   * Note: returning an empty `of()` here used to swallow the error, which made
   * downstream `firstValueFrom` throw the cryptic "EmptyError: no elements in
   * sequence" instead of the actual cause.
   */
  intercept(
    req: HttpRequest<unknown>,
    next: HttpHandler,
  ): Observable<HttpEvent<unknown>> {
    return next.handle(req).pipe(
      catchError((error: HttpErrorResponse) => {
        console.log(error);

        // Import requests pass through untouched - the service handles them.
        const isImportRequest = req.url.includes('admin/import');
        if (isImportRequest) {
          return throwError(() => error);
        }

        // Session gone: when loading an interface's data fails with 401,
        // return to the start page instead of leaving the user on a page that
        // can only render skeletons. We only redirect for a full interface load
        // (resource/<concept>/<id>/<interface>), not field-level fetches like the
        // anonymous login form's, which legitimately 401 — redirecting on those
        // would break the login page itself. The pathname guard avoids a loop
        // once we are already on the start page.
        if (error.status === 401) {
          const isInterfaceLoad = /\/resource\/[^/]+\/[^/]+\/[^/]+/.test(
            req.url,
          );
          const onPublicRoute = ['/', '/login'].includes(
            window.location.pathname,
          );
          if (isInterfaceLoad && !onPublicRoute) {
            sessionStorage.clear();
            window.location.assign('/');
          }
        }

        // Status-dependent side-effect: route a 404 to the not-found page.
        if (error.status === 404) {
          console.log('Error 404: redirecting...');
          this.router.navigate(['/404']);
        }

        // Re-throw the real error; GlobalErrorHandler turns it into a toast.
        return throwError(() => error);
      }),
    );
  }
}
