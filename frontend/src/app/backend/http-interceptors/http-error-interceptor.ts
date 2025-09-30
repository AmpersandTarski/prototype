import { Injectable } from '@angular/core';
import {
  HttpRequest,
  HttpHandler,
  HttpEvent,
  HttpInterceptor,
  HttpErrorResponse,
} from '@angular/common/http';
import { Observable, of, throwError } from 'rxjs';
import { Router } from '@angular/router';
import { catchError } from 'rxjs';
import { MessageService } from 'primeng/api';

@Injectable()
export class HttpErrorInterceptor implements HttpInterceptor {
  constructor(private router: Router, private messageService: MessageService) {}

  /** HttpErrorResponses are intercepted here. Behaviour depends on the `error.status` of the caught error. */
  intercept(
    req: HttpRequest<unknown>,
    next: HttpHandler,
  ): Observable<HttpEvent<unknown>> {
    return next.handle(req).pipe(
      catchError((error: HttpErrorResponse) => {
        console.log(error);

        // Let import requests pass through without interception - let service handle intelligence
        const isImportRequest = req.url.includes('admin/import');
        if (isImportRequest) {
          return throwError(() => error);
        }

        // Standard error handling for all non-import requests
        switch (error.status) {
          case 401:
          case 403:
          case 500: {
            this.sendErrorMessage(error, 'warn');
            break;
          }
          case 404: {
            console.log('Error 404: redirecting...');
            this.sendErrorMessage(error, 'warn');
            this.router.navigate(['/404']);
            break;
          }
          case 504: {
            throw new Error(error.message);
          }
          default: {
            this.sendErrorMessage(error, 'error');
            break;
          }
        }

        return of(); // this causes an EmptyError if a response payload is expected but not received (i.e. 504)
      }),
    );
  }

  private sendErrorMessage(error: HttpErrorResponse, severity: string) {
    this.messageService.add({
      severity: severity,
      summary: error.status.toString(),
      detail: error.error?.msg ?? error.message,
      sticky: true,
    });
  }
}
