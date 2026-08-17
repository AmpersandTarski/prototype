import { HttpClient, HttpClientModule } from '@angular/common/http';
import {
  HttpClientTestingModule,
  HttpTestingController,
  TestRequest,
} from '@angular/common/http/testing';
import { ApplicationRef, ElementRef, Renderer2 } from '@angular/core';
import { TestBed, fakeAsync, tick } from '@angular/core/testing';
import { MessageService } from 'primeng/api';
import { ObjectBase } from '../objectBase.interface';
import { InterfacesJsonService } from '../services/interfaces-json.service';
import { TransactionModeService } from '../services/transaction-mode.service';
import { TransactionService } from '../services/transaction.service';
import { AmpersandInterfaceComponent } from './ampersand-interface.class';

/**
 * The dry-run cadence of a transactional interface: one round at a time, the round that was
 * sent last decides, and the round hands the previous round's verdicts back so the backend
 * evaluates only what the newest edit affects (DesignChoices OK-10). The outcome is a
 * worklist whose entries carry whether the newest edit brought them about (OK-11).
 */
describe('AmpersandInterfaceComponent — dry-run rounds', () => {
  const ROOT = 'resource/SESSION/1/Bookings';
  const DEBOUNCE = 200; // comfortably past VALIDATION_DEBOUNCE_MS

  class TestInterface extends AmpersandInterfaceComponent<ObjectBase> {
    constructor(http: HttpClient, messageService: MessageService) {
      super(http, messageService);
      this.resource = {
        _id_: '1',
        _label_: 'x',
        _path_: ROOT,
        _ifcs_: [],
        data: { _id_: '1', _label_: 'x', _path_: ROOT, _ifcs_: [] },
      } as unknown as typeof this.resource;
    }
    /** Buffer one operation and ask for a round, as an edit on blur does. */
    public edit(name: string, value: string): void {
      type Buffering = {
        bufferPatch(
          root: string,
          ops: { op: string; path: string; value: string }[],
        ): void;
      };
      (this as unknown as Buffering).bufferPatch(ROOT, [
        { op: 'replace', path: name, value },
      ]);
    }
  }

  let http: HttpClient;
  let httpMock: HttpTestingController;
  let ifc: TestInterface;

  /** A dry-run response with the given violation messages and carried verdicts. */
  function response(messages: string[], opCount: number, verdicts = {}) {
    return {
      content: {},
      patches: [],
      notifications: {
        errors: [],
        warnings: [],
        infos: [],
        successes: [],
        invariants: messages.length
          ? [
              {
                ruleMessage: 'A confirmed booking must have a guest name.',
                tuples: messages.map((m) => ({ violationMessage: m })),
              },
            ]
          : [],
        signals: [],
      },
      invariantRulesHold: messages.length === 0,
      isCommitted: false,
      sessionRefreshAdvice: false,
      navTo: null,
      dryRunVerdicts: verdicts,
      dryRunOpCount: opCount,
    };
  }

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientModule, HttpClientTestingModule],
      providers: [
        MessageService,
        {
          provide: TransactionModeService,
          useValue: { getMode: () => 'Transactional' },
        },
        {
          provide: TransactionService,
          useValue: { setActive: () => undefined, refresh: () => undefined },
        },
        {
          provide: InterfacesJsonService,
          useValue: {
            isTransactional: () => true,
            transactionalRefPaths: () => [],
          },
        },
        {
          provide: ElementRef,
          useValue: new ElementRef(document.createElement('div')),
        },
        { provide: Renderer2, useValue: { appendChild: () => undefined } },
        {
          provide: ApplicationRef,
          useValue: {
            attachView: () => undefined,
            detachView: () => undefined,
          },
        },
      ],
    });
    http = TestBed.inject(HttpClient);
    httpMock = TestBed.inject(HttpTestingController);
    ifc = TestBed.runInInjectionContext(
      () => new TestInterface(http, TestBed.inject(MessageService)),
    );
  });

  afterEach(() => httpMock.verify({ ignoreCancelled: true }));

  function openRound(): TestRequest {
    return httpMock.expectOne((r) => r.url.includes('dryRun=true'));
  }

  it('sends the first round without carried verdicts', fakeAsync(() => {
    ifc.edit('guestName', 'Ann');
    tick(DEBOUNCE);

    const req = openRound();
    expect(req.request.body.ops).toHaveLength(1);
    expect(req.request.body.carriedOpCount).toBe(0);
    expect(req.request.body.carriedVerdicts).toEqual({});

    req.flush(response([], 1));
    tick(DEBOUNCE);
  }));

  it('hands the previous round verdicts back on the next round', fakeAsync(() => {
    ifc.edit('guestName', 'Ann');
    tick(DEBOUNCE);
    openRound().flush(
      response([], 1, { conj_1: [], conj_2: [{ src: 'b1', tgt: 'b1' }] }),
    );

    ifc.edit('confirmed', 'true');
    tick(DEBOUNCE);
    const second = openRound();
    expect(second.request.body.ops).toHaveLength(2);
    expect(second.request.body.carriedOpCount).toBe(1);
    expect(second.request.body.carriedVerdicts).toEqual({
      conj_1: [],
      conj_2: [{ src: 'b1', tgt: 'b1' }],
    });

    second.flush(response([], 2));
    tick(DEBOUNCE);
  }));

  it('collapses a burst of edits into a single round', fakeAsync(() => {
    ifc.edit('guestName', 'Ann');
    ifc.edit('confirmed', 'true');
    tick(DEBOUNCE);

    const req = openRound(); // expectOne fails if the burst produced two
    expect(req.request.body.ops).toHaveLength(2);
    req.flush(response([], 2));
    tick(DEBOUNCE);
  }));

  it('lets the round that was sent last decide, cancelling the one in flight', fakeAsync(() => {
    ifc.edit('guestName', 'Ann');
    tick(DEBOUNCE);
    const stale = openRound();

    ifc.edit('confirmed', 'true');
    tick(DEBOUNCE);
    const fresh = openRound();

    expect(stale.cancelled).toBe(true);

    fresh.flush(response(['Booking booking1 has no guest name.'], 2));
    tick(DEBOUNCE);

    expect(ifc.canSave()).toBe(false);
    expect(ifc.worklist().map((e) => e.message)).toEqual([
      'Booking booking1 has no guest name.',
    ]);
  }));

  it('keeps the carried verdicts of the last completed round when one is cancelled', fakeAsync(() => {
    ifc.edit('a', '1');
    tick(DEBOUNCE);
    openRound().flush(response([], 1, { conj_1: [] }));

    ifc.edit('b', '2'); // this round never completes: the next edit cancels it
    tick(DEBOUNCE);
    openRound();

    ifc.edit('c', '3');
    tick(DEBOUNCE);
    const third = openRound();
    // The carried state still describes the one-operation prefix that actually completed.
    expect(third.request.body.ops).toHaveLength(3);
    expect(third.request.body.carriedOpCount).toBe(1);

    third.flush(response([], 3));
    tick(DEBOUNCE);
  }));

  it('marks the entries the newest edit brought about', fakeAsync(() => {
    ifc.edit('confirmed', 'true');
    tick(DEBOUNCE);
    openRound().flush(response(['booking1 has no guest name.'], 1));
    tick(DEBOUNCE);
    expect(ifc.worklist()).toEqual([
      expect.objectContaining({
        message: 'booking1 has no guest name.',
        isNew: true,
      }),
    ]);

    ifc.edit('other', 'x');
    tick(DEBOUNCE);
    openRound().flush(
      response(
        ['booking1 has no guest name.', 'booking2 has no guest name.'],
        2,
      ),
    );
    tick(DEBOUNCE);

    // The entry that was already there is not attributed to the newest edit.
    expect(ifc.worklist()).toEqual([
      expect.objectContaining({
        message: 'booking1 has no guest name.',
        isNew: false,
      }),
      expect.objectContaining({
        message: 'booking2 has no guest name.',
        isNew: true,
      }),
    ]);
  }));

  it('drops the carried verdicts when a round fails', fakeAsync(() => {
    ifc.edit('a', '1');
    tick(DEBOUNCE);
    openRound().flush(response([], 1, { conj_1: [] }));

    ifc.edit('b', '2');
    tick(DEBOUNCE);
    openRound().flush('boom', { status: 500, statusText: 'Server Error' });
    tick(DEBOUNCE);
    expect(ifc.canSave()).toBe(false);

    ifc.edit('c', '3');
    tick(DEBOUNCE);
    const third = openRound();
    // The failed round said nothing about a longer prefix, so the last completed one stands.
    expect(third.request.body.carriedOpCount).toBe(1);
    third.flush(response([], 3));
    tick(DEBOUNCE);
  }));

  it('records the latency of each round for measurement', fakeAsync(() => {
    window.ampersandDryRunTimings = [];
    ifc.edit('a', '1');
    tick(DEBOUNCE);
    openRound().flush(response([], 1));
    tick(DEBOUNCE);

    const timings = window.ampersandDryRunTimings ?? [];
    expect(timings).toHaveLength(1);
    expect(timings[0]).toEqual(
      expect.objectContaining({ bufferSize: 1, carriedOpCount: 0 }),
    );
  }));
});
