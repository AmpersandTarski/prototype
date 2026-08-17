import { HttpHandler, HttpRequest, HttpResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { MessageService } from 'primeng/api';
import { Observable, of } from 'rxjs';
import { SignalService } from 'src/app/shared/services/signal.service';
import { LoggingInterceptor } from './logging-interceptor';

/**
 * A dry run validates an edit set the user is still filling in. Its invariant violations
 * belong on the transaction bar's worklist, not in a red toast, and its (always empty)
 * signals array must not clear the badge. See DesignChoices OK-11.
 */
describe('LoggingInterceptor', () => {
  let interceptor: LoggingInterceptor;
  let messageService: { add: jest.Mock };
  let signalService: { update: jest.Mock };

  const body = {
    content: {},
    patches: [],
    notifications: {
      errors: [],
      warnings: [],
      infos: [],
      successes: [],
      invariants: [
        {
          ruleMessage: 'A confirmed booking must have a guest name.',
          tuples: [{ violationMessage: 'Booking booking1 has no guest name.' }],
        },
      ],
      signals: [],
    },
    invariantRulesHold: false,
    isCommitted: false,
    sessionRefreshAdvice: false,
    navTo: null,
  };

  function handlerFor(response: unknown): HttpHandler {
    return {
      handle: (): Observable<HttpResponse<unknown>> =>
        of(new HttpResponse({ status: 200, body: response })),
    } as HttpHandler;
  }

  beforeEach(() => {
    messageService = { add: jest.fn() };
    signalService = { update: jest.fn() };
    TestBed.configureTestingModule({
      providers: [
        LoggingInterceptor,
        { provide: MessageService, useValue: messageService },
        { provide: SignalService, useValue: signalService },
      ],
    });
    interceptor = TestBed.inject(LoggingInterceptor);
  });

  it('raises an error toast for an invariant violation outside a transaction', async () => {
    await new Promise<void>((done) =>
      interceptor
        .intercept(
          new HttpRequest('PATCH', 'resource/SESSION/1/Bookings', []),
          handlerFor(body),
        )
        .subscribe({ complete: () => done() }),
    );

    expect(messageService.add).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error' }),
    );
  });

  it('raises no toast for a dry run', async () => {
    await new Promise<void>((done) =>
      interceptor
        .intercept(
          new HttpRequest(
            'PATCH',
            'resource/SESSION/1/Bookings?dryRun=true',
            [],
          ),
          handlerFor(body),
        )
        .subscribe({ complete: () => done() }),
    );

    expect(messageService.add).not.toHaveBeenCalled();
  });

  it('leaves the signal badge alone on a dry run', async () => {
    await new Promise<void>((done) =>
      interceptor
        .intercept(
          new HttpRequest(
            'PATCH',
            'resource/SESSION/1/Bookings?dryRun=true',
            [],
          ),
          handlerFor(body),
        )
        .subscribe({ complete: () => done() }),
    );

    expect(signalService.update).not.toHaveBeenCalled();
  });
});
