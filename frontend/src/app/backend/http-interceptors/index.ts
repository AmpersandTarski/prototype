/* "Barrel" of Http Interceptors */
import { HTTP_INTERCEPTORS } from '@angular/common/http';
import { SessionBootstrapInterceptor } from './session-bootstrap-interceptor';
import { BackendInterceptor } from './backend-interceptor';
import { LoggingInterceptor } from './logging-interceptor';
import { HttpErrorInterceptor } from './http-error-interceptor';

/** Http interceptor providers in outside-in order */
export const httpInterceptorProviders = [
  // Outermost: serialize the first backend request so the PHP session cookie
  // is established before any parallel requests go out (see the class doc).
  {
    provide: HTTP_INTERCEPTORS,
    useClass: SessionBootstrapInterceptor,
    multi: true,
  },
  { provide: HTTP_INTERCEPTORS, useClass: BackendInterceptor, multi: true },
  { provide: HTTP_INTERCEPTORS, useClass: LoggingInterceptor, multi: true },
  { provide: HTTP_INTERCEPTORS, useClass: HttpErrorInterceptor, multi: true },
];
