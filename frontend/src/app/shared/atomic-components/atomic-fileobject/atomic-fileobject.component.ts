import { Component, Inject, OnInit, ViewChild } from '@angular/core';
import { MenuItem } from 'primeng/api';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { Router } from '@angular/router';
import { InterfaceRefObject, ObjectBase } from '../../objectBase.interface';
import {
  InterfaceRouteMap,
  INTERFACE_ROUTE_MAPPING_TOKEN,
} from 'src/app/config';
import { FileObject } from 'src/app/generated/project.concepts';
import { HttpClient } from '@angular/common/http';
import { FileobjectView } from 'src/app/generated/project.views';
import { FileSelectEvent, FileUpload } from 'primeng/fileupload';

export type FullFileObject = FileObject &
  ObjectBase & { _view_: FileobjectView };

@Component({
  selector: 'app-atomic-fileobject',
  templateUrl: './atomic-fileobject.component.html',
  styleUrls: ['./atomic-fileobject.component.scss'],
})
export class AtomicFileObjectComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<FileObject, I>
  implements OnInit
{
  @ViewChild('myFileUpload') fileUpload: FileUpload;

  fileObjects: FullFileObject[] = [];

  constructor(
    private router: Router,
    @Inject(INTERFACE_ROUTE_MAPPING_TOKEN)
    private interfaceRouteMap: InterfaceRouteMap,
    private http: HttpClient,
  ) {
    super();
  }

  override ngOnInit(): void {
    super.ngOnInit();

    if (this.canUpdate()) {
      // Find which entities are able to be added to the dropdown menu
      this.interfaceComponent
        .fetchDropdownMenuData<FullFileObject>(`resource/FileObject`)
        .subscribe((objects) => {
          this.fileObjects = objects;
        });
    }
  }

  public navigateToInterface(
    interfaceName: string,
    resourceId: string,
  ): Promise<boolean> {
    const routePath = this.interfaceRouteMap[interfaceName];
    if (routePath === undefined) {
      throw new Error(`No route path defined for interface ${interfaceName}`);
    }

    return this.router.navigate([routePath, `${resourceId}`]);
  }

  /** Convert _ifcs_ to prime ng Menu */
  toPrimeNgMenuModel(
    ifcs: Array<InterfaceRefObject>,
    id: string,
  ): Array<MenuItem> {
    return ifcs.map(
      (ifc) =>
        <MenuItem>{
          label: ifc.label,
          icon: 'pi pi-refresh',
          command: () => this.navigateToInterface(ifc.id, id),
        },
    );
  }

  public deleteItem(index: number) {
    if (!confirm('Delete?')) return;

    this.interfaceComponent
      .delete(
        `${this.resource._path_}/${this.propertyName}/${this.data[index]._id_}`,
      )
      .subscribe();
  }

  /* === Input to add new link to another resource === */

  selectedFileObject: FullFileObject | undefined;

  addFileObject() {
    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'add',
          path: this.propertyName,
          value: this.selectedFileObject?._id_,
        },
      ])
      .subscribe(() => {
        this.selectedFileObject = undefined;
      });
  }

  /* === Input to upload new file === */

  uploadFile(event: FileSelectEvent) {
    for (const file of event.currentFiles) {
      const formData = new FormData();
      formData.append('file', file);

      this.http
        .post<{ content: FullFileObject }>(
          `${this.resource._path_}/${this.propertyName}`,
          formData,
        )
        .subscribe({
          next: (response) => {
            console.log('File uploaded successfully:', response);

            if (Array.isArray(this.resource[this.propertyName])) {
              this.resource[this.propertyName].push(response.content);
            } else {
              this.resource[this.propertyName] = response.content;
            }

            this.fileUpload.clear();
          },
          error: (error) => {
            console.error('There was an error uploading the file:', error);
            alert(error);
          },
        });
    }
  }
}
