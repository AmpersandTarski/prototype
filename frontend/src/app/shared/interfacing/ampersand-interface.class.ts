import { HttpClient } from '@angular/common/http';
import { catchError, Observable, share, Subject, takeUntil, tap, throwError } from 'rxjs';
import { ObjectBase } from '../objectBase.interface';
import { Patch, PatchValue } from './patch.interface';
import { PatchResponse } from './patch-response.interface';
import { DeleteResponse } from './delete-response.interface';
import { CreateResponse } from './create-response.interface';
import { Component, EventEmitter, Input, OnDestroy, Output } from '@angular/core';
import { mergeDeep } from 'src/app/shared/helper/deepmerge';
import { MessageService } from 'primeng/api';
import { ResourcePath } from '../helper/resource-path';

@Component({ template: '' })
export class AmpersandInterfaceComponent<T extends ObjectBase | ObjectBase[]> implements OnDestroy {
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

  constructor(
    protected http: HttpClient,
    private messageService: MessageService,
  ) {}

  ngOnDestroy(): void {
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
      takeUntil(this.destroy$)
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

    return this.http
      .patch<PatchResponse<T>>(rootPath.toString(), [
        ...this.pendingPatches,
        ...patches,
      ])
      .pipe(
        takeUntil(this.destroy$),
        tap((resp) => {
          if (resp.isCommitted) {
            this.pendingPatches = [];
          } else {
            this.pendingPatches.push(...patches);
          }

          /**
           * Deeply update the data object, to prevent angular from completely
           * re-rendering nested components instead of updating them.
           * This way for example cursor focus in a field is retained.
           */
          if (Array.isArray(this.resource.data)) {
            mergeDeep(
              this.resource.data.find(
                (obj) => obj._path_ === rootPath.toString(),
              ),
              resp.content,
            );
          } else {
            mergeDeep(this.resource.data, resp.content);
          }
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

  public delete(resourcePath: string): Observable<DeleteResponse> {
    return this.http.delete<DeleteResponse>(resourcePath).pipe(
      takeUntil(this.destroy$)
    );
  }
}
