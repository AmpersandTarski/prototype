import { HttpHandler, HttpRequest, HttpResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { Observable, Subject } from 'rxjs';
import { SessionBootstrapInterceptor } from './session-bootstrap-interceptor';

describe('SessionBootstrapInterceptor', () => {
  let interceptor: SessionBootstrapInterceptor;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [SessionBootstrapInterceptor],
    });
    interceptor = TestBed.inject(SessionBootstrapInterceptor);
  });

  function handlerWith(response$: Observable<HttpResponse<unknown>>): {
    handler: HttpHandler;
    handled: jest.Mock;
  } {
    const handled = jest.fn().mockReturnValue(response$);
    return { handler: { handle: handled } as unknown as HttpHandler, handled };
  }

  it('should be created', () => {
    expect(interceptor).toBeTruthy();
  });

  it('holds the second request until the first response completed', () => {
    const first$ = new Subject<HttpResponse<unknown>>();
    const first = handlerWith(first$);
    const second = handlerWith(new Subject<HttpResponse<unknown>>());

    interceptor
      .intercept(new HttpRequest('GET', 'app/navbar'), first.handler)
      .subscribe();
    interceptor
      .intercept(new HttpRequest('GET', 'resource/Foo'), second.handler)
      .subscribe();

    // First request went out; second is queued behind the bootstrap.
    expect(first.handled).toHaveBeenCalledTimes(1);
    expect(second.handled).not.toHaveBeenCalled();

    // First response arrives (session cookie established) -> second released.
    first$.next(new HttpResponse({ status: 200 }));
    first$.complete();
    expect(second.handled).toHaveBeenCalledTimes(1);
  });

  it('releases queued requests when the bootstrap request errors', () => {
    const first$ = new Subject<HttpResponse<unknown>>();
    const first = handlerWith(first$);
    const second = handlerWith(new Subject<HttpResponse<unknown>>());

    interceptor
      .intercept(new HttpRequest('GET', 'app/navbar'), first.handler)
      .subscribe({ error: () => undefined });
    interceptor
      .intercept(new HttpRequest('GET', 'resource/Foo'), second.handler)
      .subscribe();

    first$.error(new Error('backend down'));
    expect(second.handled).toHaveBeenCalledTimes(1);
  });

  it('does not queue or bootstrap on asset requests', () => {
    const asset = handlerWith(new Subject<HttpResponse<unknown>>());
    const api = handlerWith(new Subject<HttpResponse<unknown>>());

    interceptor
      .intercept(
        new HttpRequest('GET', 'assets/interfaces.json'),
        asset.handler,
      )
      .subscribe();
    interceptor
      .intercept(new HttpRequest('GET', 'app/navbar'), api.handler)
      .subscribe();

    // The asset request passed through untouched and did NOT claim the
    // bootstrap slot: the API request goes out immediately as bootstrap.
    expect(asset.handled).toHaveBeenCalledTimes(1);
    expect(api.handled).toHaveBeenCalledTimes(1);
  });
});
