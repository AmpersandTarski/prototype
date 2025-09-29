import {
  HttpEvent,
  HttpHandler,
  HttpInterceptor,
  HttpRequest,
} from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

@Injectable()
export class BackendInterceptor implements HttpInterceptor {
  intercept(
    req: HttpRequest<unknown>,
    next: HttpHandler,
  ): Observable<HttpEvent<unknown>> {
    // Don't prefix assets requests with api/v1/
    if (req.url.startsWith('/assets/') || req.url.startsWith('assets/')) {
      return next.handle(req);
    }
    
    const apiReq = req.clone({ url: `api/v1/${req.url}` });
    return next.handle(apiReq);
  }
}
