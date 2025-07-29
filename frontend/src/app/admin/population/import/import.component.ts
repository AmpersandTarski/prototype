import { Component, ViewChild } from '@angular/core';
import { HttpEventType } from '@angular/common/http';
import { FileUpload, FileUploadHandlerEvent } from 'primeng/fileupload';
import { PopulationService } from '../population.service';

@Component({
  selector: 'app-population-import',
  templateUrl: './import.component.html',
  styleUrls: ['./import.component.scss'],
})
export class ImportComponent {
  /**
   * ImportComponent allows the user to upload files containing population data.
   */
  @ViewChild('uploader') private uploader: FileUpload;

  constructor(private populationService: PopulationService) {}

  public async handleUpload(event: FileUploadHandlerEvent): Promise<void> {
    for (const file of event.files) {
      // upload files one by one
      await new Promise((resolve) => this.upload(file, resolve));
    }

    // clean up uploader state
    this.uploader.uploading = false;
    this.uploader.progress = 0;
    this.uploader.clear();
  }

  private upload(file: File, resolve: (value: unknown) => void): void {
    const uploader = this.uploader;
    this.populationService.importPopulation(file).subscribe({
      next(event) {
        switch (event.type) {
          case HttpEventType.UploadProgress: {
            if (event.loaded && typeof event.total === 'number') {
              // show the progress separate for each file,
              // starting all over agina from 0 again on the next file
              uploader.progress = Math.round(
                (event.loaded * 100) / event.total,
              );
            }
            break;
          }
          case HttpEventType.Response:
            // we could check the response status here, but any http error will
            // be caught by the http error interceptor, and runtime we'll never get here in that case...
            uploader.uploadedFiles.push(file);
            resolve(true); // resolve the promise to continue with the next file
            break;
        }
        uploader.cd.markForCheck();
      },
    });
  }
}
