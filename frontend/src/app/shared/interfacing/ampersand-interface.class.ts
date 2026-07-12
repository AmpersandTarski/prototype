import { HttpClient } from '@angular/common/http';
import {
  catchError,
  forkJoin,
  map,
  Observable,
  of,
  share,
  Subject,
  switchMap,
  takeUntil,
  tap,
  throwError,
} from 'rxjs';
import { ObjectBase } from '../objectBase.interface';
import { Patch, PatchValue } from './patch.interface';
import { PatchResponse } from './patch-response.interface';
import { DeleteResponse } from './delete-response.interface';
import { CreateResponse } from './create-response.interface';
import {
  ApplicationRef,
  Component,
  ComponentRef,
  ElementRef,
  EnvironmentInjector,
  EventEmitter,
  HostBinding,
  Input,
  OnDestroy,
  Output,
  Renderer2,
  createComponent,
  inject,
} from '@angular/core';
import { TransactionBarComponent } from 'src/app/layout/transaction-bar/transaction-bar.component';
import { mergeDeep, ProtectFn } from 'src/app/shared/helper/deepmerge';
import { isEditing } from '../helper/edit-registry';
import { MessageService } from 'primeng/api';
import { ResourcePath } from '../helper/resource-path';
import {
  TransactionMode,
  TransactionModeService,
} from '../services/transaction-mode.service';
import {
  TransactionService,
  TransactionalInterface,
} from '../services/transaction.service';
import { InterfacesJsonService } from '../services/interfaces-json.service';

@Component({ template: '' })
export class AmpersandInterfaceComponent<T extends ObjectBase | ObjectBase[]>
  implements OnDestroy, TransactionalInterface
{
  @Input() resourceId?: string;
  public resource: ObjectBase & {
    data: T;
  };
  public typeAheadData: { [path: string]: Observable<Array<ObjectBase>> } = {};
  @Output() patched = new EventEmitter<void>();

  // Subject for managing subscriptions lifecycle
  private destroy$ = new Subject<void>();

  /**
   * These patches weren't committed by the backend yet because some
   * validation rules failed.
   */
  private pendingPatches: (Patch | PatchValue)[] = [];

  // Resolved via inject() so the generated subclasses' super(http, messageService)
  // constructor contract stays intact (they do not pass these services).
  private modeService = inject(TransactionModeService);
  private txnService = inject(TransactionService);
  private interfacesJson = inject(InterfacesJsonService);

  // Used to render the SAVE/CANCEL bar inside this interface's own (accent-bordered)
  // host element, instead of a global bar at the bottom of the window.
  private elementRef = inject(ElementRef) as ElementRef<HTMLElement>;
  private envInjector = inject(EnvironmentInjector);
  private appRef = inject(ApplicationRef);
  private renderer = inject(Renderer2);
  private barRef?: ComponentRef<TransactionBarComponent>;

  /** Explicit mode override; when unset the mode is derived from `isTransactional`. */
  @Input() transactionMode?: TransactionMode;
  /** Interface name (matches interfaces.json `.name`); drives mode resolution. */
  public interfaceName?: string;

  /** Buffered, retargeted patch ops awaiting an explicit SAVE (Transactional mode). */
  private buffer: { rootPath: string; op: Patch | PatchValue }[] = [];
  private _canSave = false;
  /** Concrete invariant violations from the last dry-run, shown on a disabled SAVE. */
  private _violations: string[] = [];

  /**
   * Marks the host element of a transactional interface, so `styles.scss` can draw
   * the accent border required by the TRANSACTIONAL feature (also for subinterfaces).
   */
  @HostBinding('class.ampersand-transactional-interface')
  get isTransactionalHost(): boolean {
    return this.resolveMode() === 'Transactional';
  }

  constructor(
    protected http: HttpClient,
    private messageService: MessageService,
  ) {}

  ngOnDestroy(): void {
    // Release ownership of the open transaction, if any.
    if (this.txnService.active === this) {
      this.txnService.setActive(null);
    }

    // Tear down the mounted SAVE/CANCEL bar.
    if (this.barRef) {
      this.appRef.detachView(this.barRef.hostView);
      this.barRef.destroy();
      this.barRef = undefined;
    }

    // Clear the typeAheadData cache to prevent memory leaks
    this.typeAheadData = {};

    // Complete the destroy subject to clean up all subscriptions
    this.destroy$.next();
    this.destroy$.complete();
  }

  public setResource(resourceType: string, resourceId: string, data: T) {
    this.resource = {
      _id_: resourceId,
      _label_: `${resourceId}[${resourceType}]`,
      _path_: `resource/${resourceType}/${resourceId}`,
      _ifcs_: [],
      data: data,
    };

    // A transactional interface opens its transaction on entry: register as the
    // active interface and mount the SAVE/CANCEL bar inside this interface's own
    // accent-bordered host element (not a global bar at the window bottom), per the
    // TRANSACTIONAL feature.
    if (this.resolveMode() === 'Transactional') {
      this.txnService.setActive(this);
      this.mountTransactionBar();
    }
  }

  /**
   * Render the SAVE/CANCEL bar as the last child of this interface's host element,
   * so it sits within the transactional accent border of the (sub-)interface the
   * transaction applies to, rather than in a global bar spanning the whole window.
   */
  private mountTransactionBar(): void {
    if (this.barRef) {
      return;
    }
    this.barRef = createComponent(TransactionBarComponent, {
      environmentInjector: this.envInjector,
    });
    this.appRef.attachView(this.barRef.hostView);
    this.renderer.appendChild(
      this.elementRef.nativeElement,
      this.barRef.location.nativeElement,
    );
  }

  public fetchDropdownMenuData<ResponseObject extends ObjectBase>(
    path: string,
  ): Observable<Array<ResponseObject>> {
    if (!(path in this.typeAheadData)) {
      const source = this.http.get<Array<ResponseObject>>(path);
      // Use takeUntil to automatically clean up the observable when component is destroyed

      this.typeAheadData[path] = source.pipe(takeUntil(this.destroy$), share());
    }
    return this.typeAheadData[path] as Observable<Array<ResponseObject>>;
  }

  public post(path: string): Observable<CreateResponse> {
    return this.http.post<CreateResponse>(path, {}).pipe(
      takeUntil(this.destroy$),
      // After POST: sync with server so exec-engine side effects become visible.
      // See: https://github.com/AmpersandTarski/prototype/issues/298
      switchMap((resp) => this.syncWithServer().pipe(map(() => resp))),
    );
  }

  public patch(
    path: string,
    patches: Array<Patch | PatchValue>,
  ): Observable<PatchResponse<T>> {
    if (!this.resource.data) throw 'Cannot patch with no data set';

    const resourcePath = new ResourcePath(path);

    /**
     * Patches are targeted to the resource as indicated by the `path` parameter of this method
     * The backend API returns the updated interface data for that resource after patching.
     * Other fields can have updated values due to fired exec engine rules or derived fields.
     * Therefore we want to apply the patch to the root resource of the interface, to get the current value
     * for all fields on the interface.
     * To apply the patch to the root resource, we retarget the patch by prepending the resource path to
     * the path value in the patch data. E.g.
     * PATCH `http://example.com/api/v1/resource/123/sub/abc` { op: 'replace', path: '456', value: 'test' }
     * becomes:
     * PATCH `http://example.com/api/v1/resource/123' { op: 'replace', path: 'sub/abc/456', value: 'test' }
     * Notice that patch is applied to a higher level resource, and that the relative path is prepended in the patch data.
     */

    const ifcIsList = Array.isArray(this.resource.data);
    let rootPath = Array.isArray(this.resource.data)
      ? new ResourcePath(this.resource.data[0]!._path_).init() // remove arbitray resource id (we picked the first in the list)
      : new ResourcePath(this.resource.data._path_);

    if (!path.startsWith(rootPath.toString())) {
      console.log(path, rootPath.toString());
      throw 'Cannot patch here, because rootPath does not match';
    }

    // Determine the part of the resource path that must be moved to the patch path (see explanation above)
    let extra = resourcePath.drop(rootPath);

    // For lists the rootPath must be appended with the correct resource id (first part of 'extra')
    // and the 'extra' part must be shortened
    if (ifcIsList) {
      const resourcePart = extra.head();

      rootPath = rootPath.append(resourcePart);
      extra = extra.drop(resourcePart);
    }

    // Now we can adapt the patches
    for (const patch of patches) {
      patch.path = extra + '/' + patch.path;
    }

    // Transactional mode: buffer the (already retargeted) ops and defer the
    // commit to an explicit SAVE.
    if (this.resolveMode() === 'Transactional') {
      return this.bufferPatch(rootPath.toString(), patches);
    }

    return this.http
      .patch<PatchResponse<T>>(rootPath.toString(), [
        ...this.pendingPatches,
        ...patches,
      ])
      .pipe(
        takeUntil(this.destroy$),
        switchMap((resp) => {
          if (resp.isCommitted) {
            this.pendingPatches = [];
          } else {
            this.pendingPatches.push(...patches);
          }

          // After PATCH: sync with server for full reconciliation.
          // This makes exec-engine side effects on sibling items and derived fields
          // visible without a manual page refresh.
          // See: https://github.com/AmpersandTarski/prototype/issues/298
          return this.syncWithServer().pipe(map(() => resp));
        }),
      )
      .pipe(tap(() => this.patched.emit()))
      .pipe(
        catchError((error) => {
          this.messageService.clear();

          this.messageService.add({
            severity: 'error',
            summary: `HTTP error ${error.status}`,
            detail: error.msg,
            sticky: true,
          });

          return throwError(() => error);
        }),
      );
  }

  /** Memoized `isTransactional` lookup, keyed by the interface name it was read for. */
  private _declaredMode?: { name?: string; mode?: TransactionMode };

  private resolveMode(): TransactionMode {
    return this.modeService.getMode(this.interfaceName, this.declaredMode());
  }

  /**
   * Mode declared for this interface: an explicit `transactionMode` input if set,
   * otherwise derived from the `isTransactional` flag in interfaces.json. The flag
   * lookup is memoized because `resolveMode()` runs on every change-detection cycle.
   */
  private declaredMode(): TransactionMode | undefined {
    if (this.transactionMode) return this.transactionMode;
    if (!this.interfaceName) return undefined;
    if (this._declaredMode?.name !== this.interfaceName) {
      this._declaredMode = {
        name: this.interfaceName,
        mode: this.interfacesJson.isTransactional(this.interfaceName)
          ? 'Transactional'
          : 'Direct',
      };
    }
    return this._declaredMode.mode;
  }

  // ----- Transactional (buffered) editing -----------------------------------
  // The transaction boundary is this interface. In Transactional mode, patch ops
  // are buffered instead of committed; SAVE flushes them as one transaction,
  // CANCEL (or navigating away) discards them. POST/DELETE stay immediate in v1.

  get transactionLabel(): string {
    return this.resource?._label_ ?? 'interface';
  }

  isDirty(): boolean {
    return this.buffer.length > 0;
  }

  canSave(): boolean {
    // An empty buffer is trivially consistent; otherwise SAVE is enabled only when
    // the last dry-run found no invariant violations.
    return this.buffer.length === 0 || this._canSave;
  }

  /** Concrete invariant-violation messages that currently block SAVE (hover text). */
  violations(): string[] {
    return this._violations;
  }

  private bufferPatch(
    rootPath: string,
    patches: Array<Patch | PatchValue>,
  ): Observable<PatchResponse<T>> {
    for (const op of patches) {
      this.buffer.push({ rootPath, op });
    }
    this.txnService.setActive(this);
    // Atomic components already reflect the edit locally (Angular binding); we only
    // dry-run the buffer to decide whether SAVE may be enabled.
    this.runValidation();
    this.patched.emit();
    return of(this.bufferedResponse());
  }

  /** Synthetic response for a buffered (not-yet-committed) edit. */
  private bufferedResponse(): PatchResponse<T> {
    return {
      content: this.resource.data,
      patches: [],
      notifications: undefined,
      invariantRulesHold: true,
      isCommitted: false,
      sessionRefreshAdvice: false,
      navTo: null,
    } as unknown as PatchResponse<T>;
  }

  private groupByRoot(): { rootPath: string; ops: (Patch | PatchValue)[] }[] {
    const groups: { rootPath: string; ops: (Patch | PatchValue)[] }[] = [];
    for (const entry of this.buffer) {
      let group = groups.find((g) => g.rootPath === entry.rootPath);
      if (!group) {
        group = { rootPath: entry.rootPath, ops: [] };
        groups.push(group);
      }
      group.ops.push(entry.op);
    }
    return groups;
  }

  private dryRunUrl(rootPath: string): string {
    return rootPath + (rootPath.includes('?') ? '&' : '?') + 'dryRun=true';
  }

  /** Dry-run the buffer (no commit) to enable/disable SAVE and collect violations. */
  private runValidation(): void {
    const groups = this.groupByRoot();
    if (groups.length === 0) {
      this._canSave = false;
      this._violations = [];
      this.txnService.refresh();
      return;
    }
    const checks = groups.map((g) =>
      this.http
        .patch<PatchResponse<T>>(this.dryRunUrl(g.rootPath), g.ops)
        .pipe(catchError(() => of(null))),
    );
    forkJoin(checks)
      .pipe(takeUntil(this.destroy$))
      .subscribe((responses) => {
        // A failed dry-run (null) is treated as "not saveable".
        this._canSave = responses.every((r) => r != null && r.invariantRulesHold);
        this._violations = responses.flatMap((r) => this.collectViolations(r));
        this.txnService.refresh();
      });
  }

  /** Flatten a dry-run response's invariant notifications into human-readable lines. */
  private collectViolations(resp: PatchResponse<T> | null): string[] {
    const invariants = resp?.notifications?.invariants ?? [];
    return invariants.flatMap((inv) =>
      inv.tuples.length === 0
        ? [inv.ruleMessage]
        : inv.tuples.map((t) => t.violationMessage || inv.ruleMessage),
    );
  }

  /** Commit the buffered edits (one transaction per root resource). */
  public save(): Observable<unknown> {
    const groups = this.groupByRoot();
    if (groups.length === 0) return of(null);
    const sends = groups.map((g) =>
      this.http
        .patch<PatchResponse<T>>(g.rootPath, g.ops)
        .pipe(takeUntil(this.destroy$)),
    );
    return forkJoin(sends).pipe(
      switchMap((responses) => {
        const allCommitted = responses.every((r) => r.isCommitted);
        if (allCommitted) {
          this.buffer = [];
          this._canSave = false;
          this._violations = [];
          // Keep the transaction open (bar/border stay visible) for further edits,
          // unless the interface is being left — ngOnDestroy releases ownership.
          this.txnService.refresh();
        } else {
          this.messageService.add({
            severity: 'warn',
            summary: 'Not saved',
            detail: 'The changes violate one or more rules.',
            sticky: true,
          });
        }
        // Reconcile with the server (exec-engine effects, derived fields).
        return this.syncWithServer().pipe(map(() => responses));
      }),
      tap(() => this.patched.emit()),
      catchError((error) => {
        this.messageService.clear();
        this.messageService.add({
          severity: 'error',
          summary: `HTTP error ${error.status}`,
          detail: error.msg,
          sticky: true,
        });
        return throwError(() => error);
      }),
    );
  }

  /** Discard the buffered edits and restore the server state (rollback). */
  public cancel(): void {
    this.buffer = [];
    this._canSave = false;
    this._violations = [];
    // The interface stays transactional and open; only the buffer is discarded.
    this.txnService.refresh();
    this.syncWithServer()
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => this.patched.emit());
  }

  /**
   * An explicit action (e.g. a PROPBUTTON) that should take effect now.
   * In Direct mode this is a plain patch. In Transactional mode the action's op
   * is buffered and the whole buffer is flushed immediately — i.e. the button
   * acts as the SAVE — so single-click forms (like login) keep working.
   */
  public commitAction(
    path: string,
    patches: Array<Patch | PatchValue>,
  ): Observable<unknown> {
    if (this.resolveMode() === 'Transactional') {
      this.patch(path, patches); // buffers the op synchronously
      return this.save(); // flush the whole buffer as one transaction
    }
    return this.patch(path, patches);
  }

  public delete(resourcePath: string): Observable<DeleteResponse> {
    return this.http.delete<DeleteResponse>(resourcePath).pipe(
      takeUntil(this.destroy$),
      switchMap((deleteResp) =>
        // After DELETE: sync with server so cascade deletes and rollbacks are reflected.
        // See: https://github.com/AmpersandTarski/prototype/issues/298
        this.syncWithServer().pipe(map(() => deleteResp)),
      ),
      tap(() => this.patched.emit()),
      catchError((error) => {
        this.messageService.clear();
        this.messageService.add({
          severity: 'error',
          summary: `HTTP error ${error.status}`,
          detail: error.msg,
          sticky: true,
        });
        return throwError(() => error);
      }),
    );
  }

  /**
   * Re-fetches the full interface data from the server and reconciles it
   * with the in-memory resource.data in-place.
   *
   * Strategy (preserves object references to avoid Angular re-rendering components
   * and losing e.g. input focus):
   * - Existing items: updated via mergeDeep
   * - Items absent from server response: removed from the array
   * - Items new in server response: appended to the array
   *
   * This is the interim fix for issue #298: it ensures that exec-engine side effects
   * on sibling items, cascaded deletes, and derived-field changes become visible in the
   * UI after every mutation without a manual Cmd+R.
   * See: https://github.com/AmpersandTarski/prototype/issues/298
   */
  private syncWithServer(): Observable<void> {
    // Do not overwrite a field the user is currently editing (has focus).
    // See edit-registry.ts + issue #298.
    const protect: ProtectFn = (obj, key) =>
      !!obj?._path_ && isEditing(obj._path_, key);
    if (Array.isArray(this.resource.data)) {
      const list = this.resource.data as ObjectBase[];
      if (list.length === 0) {
        // Cannot determine the list path from an empty array; skip sync.
        // This can occur when the user creates the very first item in a list.
        return of(undefined);
      }

      const refreshPath = new ResourcePath(list[0]!._path_).init().toString();
      return this.http.get<ObjectBase[]>(refreshPath).pipe(
        takeUntil(this.destroy$),
        tap((freshList) => {
          // Remove items that are no longer present in the server response
          for (let i = list.length - 1; i >= 0; i--) {
            if (!freshList.some((f) => f._path_ === list[i]._path_)) {
              list.splice(i, 1);
            }
          }
          // Update existing items and append items that are new in the server response
          for (const fresh of freshList) {
            const existing = list.find((obj) => obj._path_ === fresh._path_);
            if (existing) {
              mergeDeep(existing, fresh, protect);
            } else {
              list.push(fresh);
            }
          }
        }),
        map(() => undefined),
      );
    } else {
      const refreshPath = (this.resource.data as ObjectBase)._path_;
      return this.http.get<ObjectBase>(refreshPath).pipe(
        takeUntil(this.destroy$),
        tap((freshData) => mergeDeep(this.resource.data, freshData, protect)),
        map(() => undefined),
      );
    }
  }
}
